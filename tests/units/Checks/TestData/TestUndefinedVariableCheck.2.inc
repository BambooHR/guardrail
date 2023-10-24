<?php

class TestClass {
	static function getInstance() {
		static $instance = null;
		if (!isset($instance)) {
			$instance = new static();
		}

		return $instance;
	}
}