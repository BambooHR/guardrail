<?php
use OpenApi\Attributes as OA;


class MyController {
	#[OA\Get(path: "/test")]
	public function withoutSearchPhrases() {
		return false;
	}

	#[OA\Get(path: "/test", x: ['vector-search-phrases' => []])]
	public function withEmptySearchPhrases() {
		return false;
	}

	#[OA\Get(path: "/test", x: ['vector-search-phrases' => ['my test search phrase']])]
	public function withSearchPhrases() {
		return false;
	}
}