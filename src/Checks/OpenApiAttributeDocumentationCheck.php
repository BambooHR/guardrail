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
	private const string SEARCH_PHRASES_KEY = 'vector-search-phrases';
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
		if ($node instanceof Node\Stmt\ClassMethod && $this->isControllerMethod($inside) && $node->isPublic() && $node->name->name !== '__construct') {
			foreach ($node->attrGroups as $attrGroup) {
				foreach ($attrGroup->attrs as $attribute) {
					$attributeName = $attribute?->name?->toString();
					if (str_starts_with($attributeName, self::ATTRIBUTE_NAMESPACE)) {
						$hasDescription = false;
						foreach ($attribute->args as $arg) {
							$this->checkDeprecatedAttribute($arg, $fileName, $node);
							$hasDescription = $hasDescription ?: $this->hasDescription($arg);
						}
						if (!$hasDescription) {
							$this->emitErrorOnLine(
								$fileName,
								$node->getLine(),
								ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK,
								"OpenAPI Attribute must have a description. Method: {$node->name->name}"
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

	private function isControllerMethod(ClassLike $inside = null): bool {
		if ($inside instanceof Class_) {
			$parentClass = $inside->extends?->toString();
			if (str_contains($parentClass, self::BASE_CONTROLLER)) {
				return true;
			}
			if ($inside->extends instanceof Node\Name) {
				$parentClass = $this->symbolTable->getClass($inside->extends);
				return $this->isControllerMethod($parentClass);
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
}