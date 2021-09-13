<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractionClass;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

class UseStatementCaseCheck extends BaseCheck {
	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [ Node\Stmt\Use_::class ];
	}

	function run($fileName, Node $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof Node\Stmt\Use_ && ($node->type == Use_::TYPE_NORMAL)) {
			foreach ($node->uses as $useNode) {
				$this->verifyCaseOfUseStatement($useNode, $fileName);
			}
		}
		if ($node instanceof Node\Stmt\Use_ && ($node->type == Use_::TYPE_UNKNOWN)) {
			foreach ($node->uses as $useNode) {
				if ($useNode->type == Use_::TYPE_NORMAL) {
					$this->verifyCaseOfUseStatement($useNode, $fileName);
				}
			}
		}
	}

	function verifyCaseOfUseStatement(UseUse $useNode, string $fileName) {
		$type = $useNode->name;
		/** @var AbstractionClass */
		$class = $this->symbolTable->getAbstractedClass(strtolower($type));
		if ($class && $type->toString() !== $class->getName()) {
			$this->emitError($fileName, $useNode, ErrorConstants::TYPE_USE_CASE_SENSITIVE, "Use statement must use the same case as the class declaration: " . $type->toString() . ' !== ' . $class->getName());
		}
	}
}