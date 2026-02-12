<?php

class MethodStringTestClass2 {
	public function ok() {
	}
}

$routerTarget = MethodStringTestClass2::class . "@missing";
