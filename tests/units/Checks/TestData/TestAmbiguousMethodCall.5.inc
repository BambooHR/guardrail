<?php

interface InterfaceA {
	public function method();
}

interface InterfaceB {
	public function method();
}

function test(InterfaceA|InterfaceB $obj) {
	return $obj->method();
}
