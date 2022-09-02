<?php

class TestClassWithMethod {
	//No errors should be emitted from this method
	static public function testGetErrorsTernary(Exception $exception): array {
		return (method_exists($exception, 'getErrors')) ? $exception->getErrors() : [];
	}

	//No errors should be emitted from this method
	static public function testGetErrorsConditional(Exception $exception): array {
		$errors = [];
		if (method_exists($exception, 'getErrors')) {
			$errors = $exception->getErrors();
		}

		return $errors;
	}

	//This method should emit a ErrorConstants::TYPE_UNKNOWN_METHOD due to the second conditional
	static public function testGetErrorsConditionalWrongMethodCheck(Exception $exception, bool $somethingElse): array {
		//Add another conditional just to make sure the logic is correct in MethodCall.php
		if ($somethingElse || method_exists($exception, 'getErrors')) {
			return [];
		}
		$errors = [];
		if (method_exists($exception, 'getFailures')) { //This should emit a ErrorConstants::TYPE_UNKNOWN_METHOD
			$errors = $exception->getErrors();
		}

		return $errors;
	}
}
