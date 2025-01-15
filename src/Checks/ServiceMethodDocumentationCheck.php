<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class ServiceMethodDocumentationCheck extends BaseCheck {

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
			foreach ($node->getParams() as $param) {
				if ($param->type instanceof Node\UnionType || $param->type instanceof Node\IntersectionType) {
					foreach ($param->type->types as $type) {
						$this->checkParamTyping($type, $node, $fileName, $inside);
					}
				}
				else {
					$this->checkParamTyping($param->type, $node, $fileName, $inside);
				}
			}
			$returnType = $node->getReturnType();
			if ($returnType instanceof Node\UnionType || $returnType instanceof Node\IntersectionType) {
				foreach ($returnType->types as $type) {
					$this->checkReturnTyping($type->name ?? $type->toString(), $node, $fileName, $inside);
				}
			} else {
				$this->checkReturnTyping($returnType, $node, $fileName, $inside);
			}
		}
	}

	private function checkParamTyping($type, Node\Stmt\ClassMethod $node, string $fileName, Node\Stmt\ClassLike $inside) {
		if (in_array($type?->name ?? $type?->toString() ?? null, [null, 'mixed', 'object'])) {
			$this->emitErrorOnLine(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
				"Method: {$node->name->name}, Class: {$inside->name->name} - All public Service methods should have strong types for all parameters. 'Mixed' and 'object' params are prohibited."
			);
		}
	}

	private function checkReturnTyping($type, Node\Stmt\ClassMethod $node, string $fileName, Node\Stmt\ClassLike $inside) {
		if (in_array($type, [null, 'mixed', 'object'])) {
			$this->emitErrorOnLine(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
				"Method: {$node->name->name}, Class: {$inside->name->name} - All public Service methods should have a return type. 'Mixed' and 'object' return types are prohibited."
			);
		}
	}
}