<?php

class ParentClass {
	public function methodOne() { }
}

class ChildClass extends ParentClass {
    /** @guardrail-ignore Standard.Incorrect.Dynamic */
	public function methodOne() {
		$callable = function() {
			return parent::methodOne();//this is legal. But static analysis can't guarantee it. closures can be rebound after creation.
		};
		return $callable();
	}
}

$callable = function() {
    return parent::methodOne();
};
$callable();
