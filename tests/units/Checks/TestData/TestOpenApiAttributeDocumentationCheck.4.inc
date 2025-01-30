<?php
use OpenApi\Attributes as OA;


class MyController extends BaseController {
	#[OA\Get(path: "/test", x: ['team-name' => 'my-team'])]
	public function withoutDescription() {
		return false;
	}

	#[OA\Get(path: "/test", description: '', x: ['team-name' => 'my-team'])]
	public function withEmptyDescription() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'test')]
	public function withoutXTag() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'test', x: [])]
	public function withoutTeamName() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'test', x: ['team-name' => ''])]
	public function withEmptyTeamName() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'this is my great description',  x: ['team-name' => 'my-team'])]
	public function withAllProperties() {
		return false;
	}
}