<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

class ServiceMethodDocumentationCheck extends BaseCheck {

	function __construct($index, $output, private MetricOutputInterface $metricOutput) {
		parent::__construct($index, $output);
	}

	private const array BLOCKED_SERVICE_DOCUMENTATION_TYPES = [null, ...self::NULLABLE_BLOCKED_SERVICE_DOCUMENTATION_TYPES];
	private const array NULLABLE_BLOCKED_SERVICE_DOCUMENTATION_TYPES = ['mixed', 'object'];
	private const string BASE_SERVICE = 'BaseService';
	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Node\Stmt\ClassMethod::class, Node\Stmt\Function_::class];
	}

	/**
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		$this->emitMetricsForNode($node, $inside);
		if ($node instanceof Node\Stmt\ClassMethod && $this->isServiceClass($inside) && $node->isPublic()) {
			$docComment = $node->getDocComment();
			if (empty($docComment)) {
				$this->emitMissingDocBlockError($fileName, $node, $inside);
				return;
			}

			$docCommentData = $this->extractDocCommentData($docComment);
			$actualParams = array_map(fn($param) => $param->var, $node->getParams());
			$docCommentParams = $docCommentData['params'];

			$this->validateParameters($actualParams, $docCommentParams, $fileName, $node, $inside);
			$this->validateReturnType($node, $docCommentData['return'], $fileName, $inside);
		}
	}

	private function isServiceClass(?ClassLike $inside = null) {
		if ($inside instanceof Class_) {
			$parentClass = $inside->extends?->toString();
			if ($parentClass !== null && str_contains($parentClass, self::BASE_SERVICE)) {
				return true;
			}
			if ($inside->extends instanceof Node\Name) {
				$parentClass = $this->symbolTable->getClass($inside->extends);
				return $this->isServiceClass($parentClass);
			}
		}

		return false;
	}

	private function emitMissingDocBlockError(string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside): void {
		$this->emitErrorOnLine($fileName, $node->getLine(),
			ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
			"Method: {$node->name?->name}, Class: {$inside?->name?->name} - All public Service methods must have a DocBlock."
		);
	}

	/**
	 * @param Node           $node
	 * @param ClassLike|null $inside
	 *
	 * @return void
	 */
	private function emitMetricsForNode(Node $node, ?Node\Stmt\ClassLike $inside): void {
		$docComment = $node->getDocComment()?->getText();
		if ($docComment !== null && str_contains($docComment, '@deprecated')) {
			$this->metricOutput->emitMetric(new Metric(
				$node->name,
				$node->getLine(),
				ErrorConstants::TYPE_METRICS_DEPRECATED_SERVICE_METHODS,
				["name" => $this->getNodeName($node, $inside)]
			));
		}
	}

	public function getNodeName(Node $node, ?Node\Stmt\ClassLike $inside) {
		if ($node instanceof Node\Stmt\ClassMethod) {
			$className = isset($inside) && isset($inside->name) ? strval($inside->name) : "(anonymous)";
			$name = $className . ($node->isStatic() ? "->" : "::") . $node->name;
		} else {
			/** @var Node\Stmt\Function_ $node */
			$name = strval($node->name);
		}

		return $name;
	}

	private function validateParameters($actualParams, $docCommentParams, string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside): void {
		if (count($docCommentParams) > count($actualParams)) {
			$this->emitErrorOnLine($fileName, $node->getLine(),
				ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
				"Method: {$node->name->name}, Class: {$inside?->name?->name} - There are extra parameters in your DocBlock that are not present in the method signature."
			);
		}

		foreach ($actualParams as $actualParam) {
			$this->validateParameter($actualParam, $docCommentParams, $fileName, $node, $inside);
		}
	}

	private function validateParameter($actualParam, $docCommentParams, string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside): void {
		$actualParamName = $actualParam->name ?? $actualParam->getString();
		$docCommentParam = $docCommentParams[$actualParamName] ?? null;
		if (!$docCommentParam) {
			$this->emitParameterMismatchError($fileName, $node, $inside, $actualParamName);
			return;
		}

		$actualParamAttribute = $actualParam->getAttribute('inferrer-type');
		if ($this->isComplexType($actualParamAttribute)) {
			$this->validateComplexType($actualParamAttribute->types, $docCommentParam['type'], $fileName, $node, $inside, $actualParamName);
		} elseif ($actualParamAttribute instanceof Node\NullableType) {
			$this->validateNullableType($actualParamAttribute, $docCommentParam['type'], $fileName, $node, $inside, $actualParamName);
		} else {
			$actualParamType = $actualParamAttribute?->name ?? $actualParamAttribute?->toString();
			$this->validateSimpleType($actualParamType, $docCommentParam['type'], $fileName, $node, $inside, $actualParamName);
		}
	}

	private function isComplexType($type): bool {
		return $type instanceof Node\UnionType || $type instanceof Node\IntersectionType;
	}

	private function validateComplexType($actualParamTypes, $docCommentParamType, string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside, string $propertyName): void {
		$docCommentParamType = is_array($docCommentParamType) ? $docCommentParamType : [$docCommentParamType];

		// Normalize doc comment types to handle nullable operator (?)
		$normalizedDocCommentTypes = array_merge(...array_map(function ($type) {
			$types = [];
			if (str_starts_with($type, '?')) {
				$types[] = 'null';
				$type = substr($type, 1);
			}
			if (str_ends_with($type, '[]')) {
				$types[] = 'array';
				$type = substr($type, 0, -2);
			}
			$types[] = $type;
			return $types;
		}, $docCommentParamType));

		if (count($actualParamTypes) !== count($normalizedDocCommentTypes)) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName, 'Parameter count mismatch.');
			return;
		}

		foreach ($actualParamTypes as $typeObject) {
			$actualParamType = $typeObject->name ?? $typeObject->toString();
			if (in_array($actualParamType, self::BLOCKED_SERVICE_DOCUMENTATION_TYPES)) {
				$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName, 'The following Types are not allowed: ' . implode(', ', self::NULLABLE_BLOCKED_SERVICE_DOCUMENTATION_TYPES) . ', or null');
				break;
			}
			if (!in_array($actualParamType, $normalizedDocCommentTypes)) {
				$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName, 'Complex Type Mismatch');
				break;
			}
		}
	}

	private function validateNullableType(Node\NullableType $paramType, $docCommentParamTypes, string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside, string $propertyName): void {
		$actualType = $paramType->type->name ?? $paramType->type->toString();
		$allowedTypes = [$actualType, 'null', "?$actualType"];
		$docCommentParamTypes = is_array($docCommentParamTypes) ? $docCommentParamTypes : [$docCommentParamTypes];
		foreach ($docCommentParamTypes as $docCommentType) {
			if (in_array($docCommentType, self::NULLABLE_BLOCKED_SERVICE_DOCUMENTATION_TYPES)) {
				$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName, 'The following Types are not allowed: ' . implode(', ', self::NULLABLE_BLOCKED_SERVICE_DOCUMENTATION_TYPES));
			}
			if (!in_array($docCommentType, $allowedTypes)) {
				$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName, 'Nullable Type Does Not Match DocBlock');
			}
		}
	}

	private function validateSimpleType($actualParamType, $docCommentParamType, string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside, string $paramName): void {
		if (is_array($docCommentParamType)) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $paramName, 'Multiple DocBlock Param Types specified for only one actual param type.');
		} elseif (($actualParamType === 'array' && str_ends_with($docCommentParamType, '[]') && !str_starts_with($docCommentParamType, '?'))) {
			return;
		} elseif (in_array($actualParamType, self::BLOCKED_SERVICE_DOCUMENTATION_TYPES)) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $paramName, 'The following Types are not allowed: ' . implode(', ', self::NULLABLE_BLOCKED_SERVICE_DOCUMENTATION_TYPES) . ', or null');
		} elseif (($actualParamType !== $docCommentParamType && !str_ends_with($actualParamType, "\\$docCommentParamType"))) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $paramName, "DocBlock does not match method signature.");
		}
	}

	private function emitParameterMismatchError(string $fileName, Node\Stmt\ClassMethod $node, ?Node\Stmt\ClassLike $inside, string $paramName): void {
		$this->emitErrorOnLine($fileName, $node->getLine(),
			ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
			"Method: {$node->name->name}, Class: {$inside?->name?->name} - DocBlock does not contain matching parameter: $paramName"
		);
	}

	private function emitTypeMismatchError(
		string                $fileName,
		Node\Stmt\ClassMethod $node,
		?Node\Stmt\ClassLike  $inside,
		string                $propertyName,
		string                $errorMessage
	): void {
		$this->emitErrorOnLine($fileName, $node->getLine(),
			ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
			"Method: {$node->name?->name}, Class: {$inside?->name?->name}, Property: $propertyName - $errorMessage"
		);
	}

	/**
	 * @param Node\Stmt\ClassMethod $node
	 * @param                       $docCommentReturn
	 * @param string                $fileName
	 * @param ?ClassLike            $inside
	 *
	 * @return void
	 */
	private function validateReturnType(Node\Stmt\ClassMethod $node, $docCommentReturn, string $fileName, ?Node\Stmt\ClassLike $inside): void {
		// return declarations on constructors are not allowed
		if ($node->name->name === '__construct') {
			return;
		}

		$propertyName = 'return';
		if (!$docCommentReturn) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName, "No Return Type Found");
			return;
		}

		$actualReturn = $node->getReturnType();
		if ($this->isComplexType($actualReturn)) {
			$this->validateComplexType($actualReturn->types, $docCommentReturn, $fileName, $node, $inside, $propertyName);
		} elseif ($actualReturn instanceof Node\NullableType) {
			$this->validateNullableType($actualReturn, $docCommentReturn, $fileName, $node, $inside, $propertyName);
		} else {
			$this->validateSimpleType($actualReturn?->name ?? $actualReturn?->toString() ?? null, $docCommentReturn, $fileName, $node, $inside, $propertyName);
		}
	}

	/**
	 * @param string|null $docComment
	 *
	 * @return array
	 */
	private function extractDocCommentData(?string $docComment) {
		$result = [
			'params' => [],
			'return' => null,
		];
		if (preg_match_all('/@param\s+([^\s$]+(?:\s*[\|&]\s*[^\s$]+)*)?\s*(\$[^\s]+)?/', $docComment, $paramMatches, PREG_SET_ORDER)) {
			foreach ($paramMatches as $paramMatch) {
				$variableName = ltrim($paramMatch[2] ?? null, '$');
				$result['params'][$variableName] = [
					'type' => $this->extractDocGetVariableType($paramMatch[1] ?? null),
					'variable' => $variableName,
				];
			}
		}

		if (preg_match('/@return\s+([^\s$]+(?:\s*\|\s*[^\s$]+|&[^\s$]+)*)/', $docComment, $returnMatch)) {
			$result['return'] = $this->extractDocGetVariableType($returnMatch[1] ?? null);
		}

		return $result;
	}

	private function extractDocGetVariableType($variableType) {
		if (str_contains($variableType, '|')) {
			$variableType = array_map('trim', explode('|', $variableType));
		} elseif (str_contains($variableType, '&')) {
			$variableType = array_map('trim', explode('&', $variableType));
		}

		return $variableType;
	}
}
