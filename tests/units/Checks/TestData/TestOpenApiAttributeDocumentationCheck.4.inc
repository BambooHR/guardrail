<?php
use OpenApi\Attributes as OA;


class MyController extends BaseController {
	#[OA\Get(path: "/test")]
	public function withoutDescription() {
		return false;
	}

	#[OA\Get(path: "/test", description: '')]
	public function withEmptyDescription() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'this is my great description')]
	public function withDescription() {
		return false;
	}
}