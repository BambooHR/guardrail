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

	/**
	 * @return mixed
	 */
	public function specialReturnType(): mixed {
		return 123;
	}

	// This method should emit 1 error

	/**
	 * @return object
	 */
	public function specialReturnType2(): object {
		return 123;
	}

	// This method should emit 1 error

	/**
	 * @return int|string|object
	 */
	public function unionParamTypes(): int | string | object {
		return 123;
	}

	// This method should emit 0 errors

	/**
	 * @return MyObj2&MyObj
	 */
	public function intersectionParamTypes(): MyObj&MyObj2 {
	}

	// This method should emit 0 errors

	/**
	 * @return bool
	 */
	public function withNormalTypedParams(): bool {
		return true;
	}
}

class MyObj {
}

class MyObj2 {
}