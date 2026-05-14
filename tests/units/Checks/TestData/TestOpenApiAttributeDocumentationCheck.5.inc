<?php
use OpenApi\Attributes as OA;

class BaseController {
	protected function resolveOauthApplicationId($request): ?int {
		return null;
	}
}

class MyController extends BaseController {
	// Has multi-sourced-ownership but DOES NOT call resolveOauthApplicationId — should error
	#[OA\Post(path: "/test", description: 'Creates a resource.', x: ['team-name' => 'my-team', 'multi-sourced-ownership' => true])]
	public function createMissing($request) {
		return false;
	}

	// Has multi-sourced-ownership AND calls resolveOauthApplicationId — should pass
	#[OA\Put(path: "/test/{id}", description: 'Replaces a resource.', x: ['team-name' => 'my-team', 'multi-sourced-ownership' => true])]
	public function replaceWithCall($request, $id) {
		$oauthApplicationId = $this->resolveOauthApplicationId($request);
		return $oauthApplicationId;
	}

	// Has multi-sourced-ownership: false — should NOT trigger the check
	#[OA\Delete(path: "/test/{id}", description: 'Deletes a resource.', x: ['team-name' => 'my-team', 'multi-sourced-ownership' => false])]
	public function deleteWithFalseFlag($request, $id) {
		return false;
	}

	// No multi-sourced-ownership key at all — should NOT trigger the check
	#[OA\Get(path: "/test", description: 'Lists resources.', x: ['team-name' => 'my-team'])]
	public function listNoFlag($request) {
		return false;
	}
}
