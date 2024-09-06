<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Builder\Interface_;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;

class DuplicateMemberCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [ Class_::class, Interface_::class, Enum_::class];
	}

	function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof ClassLike) {
			$members = ["method"=>[], "property"=>[], "constant"=>[]];

			foreach ($node->stmts as $stmt) {
				if ($stmt instanceof Node\Stmt\ClassMethod) {
					$this->addOrEmitError($fileName, $stmt, $members, $stmt->name->name, "method");
				} else if ($stmt instanceof Node\Stmt\Property) {
					foreach ($stmt->props as $prop) {
						$this->addOrEmitError($fileName, $prop, $members, $prop->name->name, "property");
					}
				} else if ($stmt instanceof Node\Stmt\ClassConst) {
					foreach ($stmt->consts as $const) {
						$this->addOrEmitError($fileName, $const, $members, $const->name->name, "constant");
					}
				}
			}
		}
	}

	function addOrEmitError(string $fileName, Node $node, array &$members, string $name,string $type) {
		$normalizedName = ($type == 'method') ? strtolower($name) : $name;
		if (!isset($members[$type][$normalizedName])) {
			$members[$type][$normalizedName] = $node->getLine();
		} else {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_DUPLICATE_NAME, "Duplicate $type " . $name . " first declared on line " . $members[$type][$normalizedName]);
		}
	}
}