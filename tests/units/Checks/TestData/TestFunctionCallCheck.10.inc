<?php

class HasToString {
	public function __toString(): string {
		return '';
	}
}

class NoHasToString {
}

class ClassWithStringRequirement {
	public function methodWithStringRequirement(string $required) {
	}
}

function functionWithStringRequirement(string $required) {
}

$HasToString = new HasToString();
$NoHasToString = new NoHasToString();
$ClassWithStringRequirement = new ClassWithStringRequirement();

$ClassWithStringRequirement->methodWithStringRequirement($HasToString); //Should succeed
$ClassWithStringRequirement->methodWithStringRequirement('single quote string'); //Should succeed
$ClassWithStringRequirement->methodWithStringRequirement($NoHasToString); //Should fail

functionWithStringRequirement($HasToString); //Should succeed
functionWithStringRequirement("double quote string"); //Should succeed
functionWithStringRequirement($NoHasToString); //Should fail