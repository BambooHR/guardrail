<?php

class MyTestClass {

	private $property;

	public function method($param) {
		[$first] = $param[0];

		$this->test($first, [$this->property]);
		return [
			'arrayKey' => $param,
			'secondArrayKey' => ''
		];
	}

	public function test(&$first, $second) {
		return;
	}
}
