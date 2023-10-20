<?php

class TestClass {
	protected function testMethod(): array {
		$successes = 0;
		$qryRowParams = [];
		$qryRowParamValues = [];

		return array('test' => $successes, 'one' => $qryRowParams, $qryRowParamValues, $unknown);
	}
}