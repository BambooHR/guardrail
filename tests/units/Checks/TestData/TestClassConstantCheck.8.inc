<?php

interface ParentInterface {
	const INTERFACE_CONST = 'interface';
}

class ImplementingClass implements ParentInterface {
	public function test() {
		return self::INTERFACE_CONST;
	}
}
