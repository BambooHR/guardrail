<?php

class MethodStringTestClass3 {
	public function ok() {
	}
}

$routerTarget = MethodStringTestClass3::class . "missing";
