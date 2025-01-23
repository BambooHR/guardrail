<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class ServiceMethodDocumentationCheck extends BaseCheck {

	function __construct($index, $output, private MetricOutputInterface $metricOutput) {
		parent::__construct($index, $output);
	}

	private const array BLOCKED_SERVICE_DOCUMENTATION_TYPES = [null, 'mixed', 'object'];
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
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		$this->emitMetricsForNode($node);
		if ($node instanceof Node\Stmt\ClassMethod && $node->isPublic()) {
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

	private function emitMissingDocBlockError(string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside): void {
		$this->emitErrorOnLine($fileName, $node->getLine(),
			ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
			"Method: {$node->name->name}, Class: {$inside->name->name} - All public Service methods must have a DocBlock."
		);
	}

	/**
	 * @param Node\Stmt\ClassMethod $node
	 *
	 * @return void
	 */
	private function emitMetricsForNode($node): void {
		if (str_contains($node->getDocComment()?->getText(), '@deprecated')) {
			$this->metricOutput->emitMetric(new Metric(
				$node->name,
				$node->getLine(),
				ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS,
				[]
			));
		}
	}

	private function validateParameters($actualParams, $docCommentParams, string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside): void {
		if (count($docCommentParams) > count($actualParams)) {
			$this->emitErrorOnLine($fileName, $node->getLine(),
				ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
				"Method: {$node->name->name}, Class: {$inside->name->name} - There are extra parameters in your DocBlock that are not present in the method signature."
			);
		}

		foreach ($actualParams as $actualParam) {
			$this->validateParameter($actualParam, $docCommentParams, $fileName, $node, $inside);
		}
	}

	private function validateParameter($actualParam, $docCommentParams, string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside): void {
		$actualParamName = $actualParam->name ?? $actualParam->getString();
		$docCommentParam = $docCommentParams[$actualParamName] ?? null;
		if (!$docCommentParam) {
			$this->emitParameterMismatchError($fileName, $node, $inside, $actualParamName);
			return;
		}

		$actualParamAttribute = $actualParam->getAttribute('inferrer-type');
		if ($this->isComplexType($actualParamAttribute)) {
			$this->validateComplexType($actualParamAttribute->types, $docCommentParam['type'], $fileName, $node, $inside, $actualParamName);
		} else {
			$actualParamType = $actualParamAttribute?->name ?? $actualParamAttribute?->toString();
			$this->validateSimpleType($actualParamType, $docCommentParam['type'], $fileName, $node, $inside, $actualParamName);
		}
	}

	private function isComplexType($type): bool {
		return $type instanceof Node\UnionType || $type instanceof Node\IntersectionType;
	}

	private function validateComplexType($actualParamTypes, $docCommentParamType, string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside, string $propertyName): void {
		if (!is_array($docCommentParamType) || count($actualParamTypes) !== count($docCommentParamType)) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName);
			return;
		}

		foreach ($actualParamTypes as $typeObject) {
			$actualParamType = $typeObject->name ?? $typeObject->toString();
			if (!in_array($actualParamType, $docCommentParamType) || in_array($actualParamType, self::BLOCKED_SERVICE_DOCUMENTATION_TYPES)) {
				$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName);
				break;
			}
		}
	}

	private function validateSimpleType($actualParamType, $docCommentParamType, string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside, string $paramName): void {
		if ($actualParamType !== $docCommentParamType || in_array($actualParamType, self::BLOCKED_SERVICE_DOCUMENTATION_TYPES)) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $paramName);
		}
	}

	private function emitParameterMismatchError(string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside, string $paramName): void {
		$this->emitErrorOnLine($fileName, $node->getLine(),
			ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
			"Method: {$node->name->name}, Class: {$inside->name->name} - DocBlock does not contain matching parameter: $paramName"
		);
	}

	private function emitTypeMismatchError(string $fileName, Node\Stmt\ClassMethod $node, Node\Stmt\ClassLike $inside, string $propertyName): void {
		$this->emitErrorOnLine($fileName, $node->getLine(),
			ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
			"Method: {$node->name->name}, Class: {$inside->name->name}, Property: $propertyName - DocBlock does not match method signature."
		);
	}

	/**
	 * @param Node\Stmt\ClassMethod $node
	 * @param                       $docCommentReturn
	 * @param string                $fileName
	 * @param ClassLike             $inside
	 *
	 * @return void
	 */
	private function validateReturnType(Node\Stmt\ClassMethod $node, $docCommentReturn, string $fileName, Node\Stmt\ClassLike $inside): void {
		$propertyName = 'return';
		if (!$docCommentReturn) {
			$this->emitTypeMismatchError($fileName, $node, $inside, $propertyName);
			return;
		}

		$actualReturn = $node->getReturnType();
		if ($this->isComplexType($actualReturn)) {
			$this->validateComplexType($actualReturn->types, $docCommentReturn, $fileName, $node, $inside, $propertyName);
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
				$result['params'][$variableName] = [
					'type' => $this->extractDocGetVariableType($paramMatch[1] ?? null),
					'variable' => $variableName = ltrim($paramMatch[2] ?? null, '$'),
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
		} else if (str_contains($variableType, '&')) {
			$variableType = array_map('trim', explode('&', $variableType));
		}

		return $variableType;
	}
}