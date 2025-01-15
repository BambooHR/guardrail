<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class WebApiDocumentationCheck extends BaseCheck {
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
		if ($node instanceof Node\Stmt\ClassMethod && $node->isPublic()) {
			foreach ($node->attrGroups as $attrGroup) {
				foreach ($attrGroup->attrs as $attribute) {
					$attributeName = $attribute->name->toString();
					if (str_starts_with($attributeName, 'OpenApi\Attributes')) {
						foreach ($attribute->args as $arg) {
							if ($arg->name->name === 'deprecated' && $arg->value->name->toString() == 'true') {
								$this->metricOutput->emitMetric(new Metric(
									$fileName,
									$node->getLine(),
									ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS,
									[]
								));
								break;
							}
						}

						return;
					}
				}
			}

			$className = $inside->namespacedName->toString();
			$this->emitErrorOnLine(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_WEB_API_DOCUMENTATION_CHECK,
				"All public controller methods should be associated with a route and must have 
					documentation through an OpenAPI Attribute. Method: {$node->name->name}, Class: $className"
			);
		}
	}
}