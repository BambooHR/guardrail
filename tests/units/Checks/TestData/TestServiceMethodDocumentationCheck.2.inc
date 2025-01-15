<?php

class MyService {
	// This method should emit 1 error
	/**
	 * @return int
	 */
	public function noReturnTypes() {
		return 123;
	}

	// This method should emit 1 error
	public function specialReturnType(): mixed {
		return 123;
	}

	// This method should emit 1 error
	public function specialReturnType2(): object {
		return 123;
	}

	// This method should emit 1 error
	public function unionParamTypes(): int|string|object {
		return 123;
	}

	// This method should emit 0 errors
	public function intersectionParamTypes(): MyObj&MyObj2 {
		return 123;
	}

	// This method should emit 0 errors
	public function withNormalTypedParams(): bool {
		return 123;
	}
}

class MyObj {
}

class MyObj2 {
}