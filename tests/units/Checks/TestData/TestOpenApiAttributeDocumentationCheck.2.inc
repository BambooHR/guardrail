<?php
use OpenApi\Attributes as OA;

class MyBaseController {
}
class MyController extends MyBaseController {
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