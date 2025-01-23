<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class OpenApiAttributeDocumentationCheck extends BaseCheck {
	private const string ATTRIBUTE_NAMESPACE = 'OpenApi\Attributes';
	private const string SEARCH_PHRASES_KEY = 'vector-search-phrases';
	private const string DEPRECATED_KEY = 'deprecated';
	private const string X_KEY = 'x';

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
					if (str_starts_with($attributeName, self::ATTRIBUTE_NAMESPACE)) {
						$hasDefinedSearchPhrases = false;
						foreach ($attribute->args as $arg) {
							$this->checkDeprecatedAttribute($arg, $fileName, $node);
							$hasDefinedSearchPhrases = $hasDefinedSearchPhrases ?: $this->hasVectorSearchPhrase($arg);
						}
						if (!$hasDefinedSearchPhrases) {
							$this->emitErrorOnLine(
								$fileName,
								$node->getLine(),
								ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_SEARCH_PHRASES_CHECK,
								"OpenAPI Attribute must have a vector-search-phrases key defined. Method: {$node->name->name}"
							);
						}
						return;
					}
				}
			}
			$className = $inside->namespacedName->toString();
			$this->emitErrorOnLine(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK,
				"All public controller methods should be associated with a route and must have 
					documentation through an OpenAPI Attribute. Method: {$node->name->name}, Class: $className"
			);
		}
	}

	private function checkDeprecatedAttribute($arg, $fileName, $node) {
		if ($arg->name->name === self::DEPRECATED_KEY && $arg->value->name->toString() == 'true') {
			$this->metricOutput->emitMetric(new Metric(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS,
				[]
			));
		}
	}

	private function hasVectorSearchPhrase($arg): bool {
		if ($arg->name->name === self::X_KEY && $arg->value instanceof Node\Expr\Array_) {
			foreach ($arg->value->items as $item) {
				if ($item->key->value === self::SEARCH_PHRASES_KEY) {
					return true;
				}
			}
		}

		return false;
	}
}