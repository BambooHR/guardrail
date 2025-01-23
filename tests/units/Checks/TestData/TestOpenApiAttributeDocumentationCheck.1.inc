<?php
use OpenApi\Attributes as OA;


class MyController {
	/**
	 * @return bool
	 */
	#[\Onsen\SecurityAudit\Sensitivity\Low]
	#[OA\Get(path: "/test")]
	public function hasAttribute() {
		return false;
	}

	/**
	 * @return int
	 */
	public function doesNotHaveAttribute() {
		return 123;
	}

	/**
	 * @return int
	 */
	#[\Onsen\SecurityAudit\Sensitivity\Low]
	public function hasFakeAttribute() {
		return 456;
	}
}