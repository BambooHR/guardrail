<?php

class ParentClass {
	const PARENT_CONST = 'parent';
}

class ChildClass extends ParentClass {
	public function test() {
		return self::PARENT_CONST;
	}
}
