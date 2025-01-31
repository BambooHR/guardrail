<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;

class OpenApiAttributeDocumentationCheck extends BaseCheck {
	private const string ATTRIBUTE_NAMESPACE = 'OpenApi\Attributes';
	private const string TEAM_NAME_KEY = 'team-name';
	private const string X_KEY = 'x';
	private const string DEPRECATED_KEY = 'deprecated';
	private const string DESCRIPTION_KEY = 'description';
	private const string BASE_CONTROLLER = 'BaseController';

	function __construct($index, $output, private readonly MetricOutputInterface $metricOutput) {
		parent::__construct($index, $output);
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Node\Stmt\ClassMethod::class];
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
		if ($node instanceof Node\Stmt\ClassMethod && $this->isControllerClass($inside) && $node->isPublic() && $node->name->name !== '__construct') {
			foreach ($node->attrGroups as $attrGroup) {
				foreach ($attrGroup->attrs as $attribute) {
					$attributeName = $attribute?->name?->toString();
					if (str_starts_with($attributeName, self::ATTRIBUTE_NAMESPACE)) {
						$hasDescription = false;
						$hasTeamName = false;
						foreach ($attribute->args as $arg) {
							$this->checkDeprecatedAttribute($arg, $fileName, $node);
							$hasDescription = $hasDescription ?: $this->hasDescription($arg);
							$hasTeamName = $hasTeamName ?: $this->hasTeamName($arg);
						}
						if (!$hasDescription) {
							$this->emitErrorOnLine(
								$fileName,
								$node->getLine(),
								ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK,
								"OpenAPI Attribute must have a description. Method: {$node->name->name}"
							);
						}
						if (!$hasTeamName) {
							$this->emitErrorOnLine(
								$fileName,
								$node->getLine(),
								ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_TEAM_CHECK,
								"OpenAPI Attribute must have a 'team-name' key set in the 'x' property. Method: {$node->name->name}"
							);
						}
						return;
					}
				}
			}
			$className = $inside?->namespacedName?->toString();
			$this->emitErrorOnLine(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK,
				"All public controller methods should be associated with a route and must have 
					documentation through an OpenAPI Attribute. Method: {$node->name->name}, Class: $className"
			);
		}
	}

	private function isControllerClass(ClassLike $inside = null): bool {
		if ($inside instanceof Class_) {
			$parentClass = $inside->extends?->toString();
			if (str_contains($parentClass, self::BASE_CONTROLLER)) {
				return true;
			}
			if ($inside->extends instanceof Node\Name) {
				$parentClass = $this->symbolTable->getClass($inside->extends);
				return $this->isControllerClass($parentClass);
			}
		}

		return false;
	}

	private function checkDeprecatedAttribute($arg, $fileName, $node) {
		if ($arg?->name?->name === self::DEPRECATED_KEY && $arg?->value?->name?->toString() == 'true') {
			$this->metricOutput->emitMetric(new Metric(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS,
				[]
			));
		}
	}

	private function hasDescription($arg): bool {
		return $arg->name->name === self::DESCRIPTION_KEY && !empty($arg->value->value);
	}

	private function hasTeamName($arg): bool {
		if ($arg->name->name === self::X_KEY && $arg->value instanceof Node\Expr\Array_) {
			foreach ($arg->value->items as $item) {
				if ($item->key->value === self::TEAM_NAME_KEY && !empty($item->value->value)) {
					return true;
				}
			}
		}

		return false;
	}
}