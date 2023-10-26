<?php

use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node;


class TestObject {
	public string $test;
}

class TestClass {
		private ?TestObject $testObj = null;

		function method(): string {
			$testObj = $this->getTestObj();
			if (is_null($this->testObj)) {
				return '';
			}
			if (!$testObj) {
				return '';
			}
			$this->testObj->test = $testObj->test;
			return $this->testObj->test;
		}

		function getTestObj(): ?TestObject {
			return null;
		}

		public function testThrowWithMultipleExcecutionsInIfNode(?User $user): string {
			if (!$user) {
				$userId = $contact['userId'];
				throw new InvalidDataException('User Trax Login is null');
			}

			return $user->firstName;
		}

	function onExit(Node $node)
	{
		/** @var BinaryOp $expr */
		$expr = $node;
		$sigil = $expr->getOperatorSigil();

		if ($sigil == "&&" && $expr instanceof BinaryOp\BooleanAnd) {
			$this->mergeAndScope($expr);
		}
	}

	function mergeAndScope(BinaryOp\BooleanAnd $node) {
		return;
	}

	function testIfScopePops() {
		$table = $this->getTable();
		if ($table instanceof ParentClass) {
			$table->connect(0);
		}
		$table->connect(0);
		$this->indexTraitClasses($table);
	}

	function indexTraitClasses(ChildClass $table) {

	}

	/**
	 * @return ChildClass
	 */
	function getTable(): ChildClass {
		return new ChildClass();
	}
}

class ChildClass implements ParentClass {

}

interface ParentClass {

}

class User {
	public string $firstName;
}