<?php
use OpenApi\Attributes as OA;

class BaseController {
}
class MyController extends BaseController {
	// constructors don't need an attribute
	public function __construct() {
	}

	/**
	 * @return bool
	 */
	#[\Onsen\SecurityAudit\Sensitivity\Low]
	#[OA\Get(path: "/test", description: '123', x: ['team-name' => 'my-team'])]
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