<?php

interface CommonInterface {
	public function method();
}

class ImplA implements CommonInterface {
	public function method() {}
}

class ImplB implements CommonInterface {
	public function method() {}
}

function test(ImplA|ImplB $obj) {
	return $obj->method();
}
