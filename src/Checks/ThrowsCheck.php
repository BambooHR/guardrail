<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class ThrowsCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [Node\Expr\Throw_::class, Node\Stmt\Throw_::class,
			Node\Expr\MethodCall::class,
			Node\Expr\FuncCall::class,
			Node\Expr\StaticCall::class
		];
	}

	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {

		if ($node instanceof Node\Expr\Throw_ || $node instanceof Node\Stmt\Throw_) {
			$throws = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if ($throws instanceof Node\Name) {
				if (!$this->parentCatches($scope->getParentNodes(), $throws) &&
					!$this->isDocumentedThrow($scope->getInsideFunction(), $throws)
				) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNDOCUMENTED_EXCEPTION,"Undocumented exception ($throws) thrown");
				}
			}
		} else if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
			$type=$node->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if ($type) {
				TypeComparer::forEachType($type, function($typeNode) use ($node,$scope, $fileName) {
					if ($typeNode instanceof Node\IntersectionType) {
						$methods = [];
						foreach($typeNode->types as $subType) {
							$method[] = Util::findAbstractedMethod($subType, $node->name, $this->symbolTable );
						}
					} else {
						$methods = [Util::findAbstractedMethod(strval($typeNode), $node->name, $this->symbolTable)];
					}
					foreach($methods as $method) {
						if ($method) {
							$throws = $method->getThrowsList();
							foreach ($throws as $throw) {
								if (!$this->parentCatches($scope->getParentNodes(), $throw) &&
									!$this->isDocumentedThrow($scope->getInsideFunction(), $throw)) {
									$this->emitError($fileName, $node, ErrorConstants::TYPE_UNDOCUMENTED_EXCEPTION, "Undocumented exception ($throw) thrown by " . TypeComparer::typeToString($typeNode) . "::" . $node->name);
								}
							}
						}
					}
				});
			}
		}
	}

	function isDocumentedThrow(?Node $node, string $throw) {
		if ($node) {
			$documentedThrows = $node->getAttribute('throws', []);
			foreach ($documentedThrows as $documentedThrow) {
				if ($this->symbolTable->isParentClassOrInterface($documentedThrow, $throw)) {
					return true;
				}
			}
		}
		return false;
	}

	function parentCatches($parents, $throws):bool {
		foreach (array_reverse($parents) as $parent) {
			if ($parent instanceof Node\Stmt\TryCatch) {
				foreach ($parent->catches as $catch) {
					foreach ($catch->types as $type) {
						if ($this->symbolTable->isParentClassOrInterface($type,$throws)) {
							return true;
						}
					}
				}
			} else if($parent instanceof Node\FunctionLike) {
				return false;
			}
		}
		return false;
	}
}