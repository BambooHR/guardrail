<?php
use OpenApi\Attributes as OA;


class MyController {
	/**
	 * @return bool
	 */
	#[OA\Get(path: "/test", deprecated: true)]
	public function hasDeprecatedAttribute() {
		return false;
	}

	#[OA\Get(path: "/test")]
	public function doesNotHaveDeprecatedAttribute() {
		return false;
	}
}