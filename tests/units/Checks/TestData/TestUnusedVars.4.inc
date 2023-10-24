<?php

use PhpParser\Node;

class TestClass {
	function method() {
		$test = ['123'];
		$callback = function() use(&$test) {
			$test []= '456';
		};
		$callback();
		return $test;
	}

	function testChainedCallWithArrayDimFetch(Node $node): void {
		if(count($node->declares)>0) {
			($node->declares[0]->value instanceof Node\Scalar\LNumber) ? true : false;
		}
	}

}