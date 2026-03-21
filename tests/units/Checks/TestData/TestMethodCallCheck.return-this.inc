<?php

class Builder {
	/**
	 * @return $this
	 */
	function setName($name) {
		return $this;
	}
	
	/**
	 * @return $this
	 */
	function setValue($value) {
		return $this;
	}
	
	function build() {
		return "built";
	}
}

function testReturnThis() {
	$builder = (new Builder())->setName("test");
	$builder->setValue("value");
	$builder->build();
}

function testChainedReturnThis() {
	$result = (new Builder())->setName("test")->setValue("value");
	$result->build();
}
