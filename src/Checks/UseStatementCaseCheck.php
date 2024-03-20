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
		static $classNames = [];
		$type = strval($useNode->name);
		$className = "";

		if (!isset($classNames[$type])) {

			/** @var AbstractionClass */
			if ($type) {
				$class = $this->symbolTable->getAbstractedClass($type);
				if($class) {
					$className = $class->getName();
				}
			}
			$classNames[$type] = $className;
		}
		if ($className && $type !== $className) {
			$this->emitError($fileName, $useNode, ErrorConstants::TYPE_USE_CASE_SENSITIVE, "Use statement must use the same case as the class declaration: " . $type . ' !== ' . $class->getName());
		}
	}
}