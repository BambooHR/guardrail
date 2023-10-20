<?php

class TestObject {
	public string $test;
}

class TestClass {
	private ?TestObject $testObj = null;

	function method(): string {
		$testObj = $this->getTestObj();
		if (is_null($this->testObj)) {
			return '';
		}
		if (!$testObj) {
			return '';
		}
		$this->testObj->test = $testObj->test;
		return $this->testObj->test;
	}

	function getTestObj(): ?TestObject {
		return null;
	}

	public function testThrowWithMultipleExcecutionsInIfNode(?User $user): string {
		if (!$user) {
			$userId = $contact['userId'];
			throw new InvalidDataException('User Trax Login is null');
		}

		return $user->firstName;
	}
}

class User {
	public string $firstName;
}