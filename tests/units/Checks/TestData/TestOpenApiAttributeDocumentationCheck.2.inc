<?php
use OpenApi\Attributes as OA;

class MyController {
	function undefinedVisibilityMethod() {
		return false;
	}
	public function publicMethod() {
		return false;
	}
	protected function protectedMethod() {
		return false;
	}
	private function privateMethod() {
		return false;
	}
}