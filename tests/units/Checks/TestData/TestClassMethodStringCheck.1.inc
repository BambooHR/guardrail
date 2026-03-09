<?php

class MethodStringTestClass {
	public function ok() {
	}
}

$routerTarget = MethodStringTestClass::class . "@ok";
