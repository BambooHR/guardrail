<?php

class HasToString {
	public function __toString(): string {
		return '';
	}
}

class ClassWithStringRequirement {
	public function methodWithStringRequirement(string $required) {
	}
}

function functionWithStringRequirement(string $required) {
}

$HasToString = new HasToString();

(new ClassWithStringRequirement())->methodWithStringRequirement($HasToString);
(new ClassWithStringRequirement())->methodWithStringRequirement('single quote string');

functionWithStringRequirement($HasToString);
functionWithStringRequirement("double quote string");