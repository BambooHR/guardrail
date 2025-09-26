<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;

class ClassConstCheck extends BaseCheck {
	private $comparer;
	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->comparer=new TypeComparer($symbolTable);
	}

	function getCheckNodeTypes() {
		return [ClassConst::class];
	}

	function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof ClassConst) {
			foreach($node->consts as $const) {
				/** @var Node\Const_ $const */
				$constValue = $const->value->getAttribute(TypeComparer::INFERRED_TYPE_ATTR );
				if ($node->type && $constValue) {
					if (!$this->comparer->isCompatibleWithTarget($node->type, $constValue, forceStrict: true)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_CONST_TYPE,
							 "Type mismatch between declared type (".
							 TypeComparer::typeToString($node->type) . ") and constant value (" .
							 TypeComparer::typeToString($constValue) . ")"
						);
					}
				}
			}
		}
	}
}