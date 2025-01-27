<?php
use OpenApi\Attributes as OA;

class SubController extends BaseController {
}
class MyController extends SubController {
	/**
	 * @return bool
	 */
	#[OA\Get(path: "/test", deprecated: true, description: '123')]
	public function hasDeprecatedAttribute() {
		return false;
	}

	#[OA\Get(path: "/test", description: '123')]
	public function doesNotHaveDeprecatedAttribute() {
		return false;
	}
}