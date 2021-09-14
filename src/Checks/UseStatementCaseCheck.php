<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractionClass;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

class UseStatementCaseCheck extends BaseCheck {
	/**
	 * @return string[]
	 */
	function getCheckNodeTypes():array {
		return [ Node\Stmt\Use_::class ];
	}

	function run(string $fileName, Node $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
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
		$className = $this->symbolTable->getOriginalName(SymbolTable::TYPE_CLASS) ?? $this->symbolTable->getOriginalNAme(SymbolTable::TYPE_INTERFACE);

		if ($className && $type->toString() !== $className) {
			$this->emitError($fileName, $useNode, ErrorConstants::TYPE_USE_CASE_SENSITIVE, "Use statement must use the same case as the class declaration: " . $type->toString() . ' !== ' . $className);
		}
	}
}