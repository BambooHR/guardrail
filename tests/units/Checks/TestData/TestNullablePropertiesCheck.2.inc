<?php

namespace BambooHR\Guardrail\Tests\units\Checks\TestData;

class EmptyA {
	public ?EmptyB $b;
}

class EmptyB {
	public ?EmptyC $c;
}

class EmptyC {
	public ?EmptyC $c;

	function foo():int {
		return 1;
	}
}

class Container {

	private function testPropertyIsNotNull(?EmptyA $param) {
		if (isset($param->b->c->c)) {
			// Note: $param can't be null, because the larger expression isn't null.
			echo $param->b->c->c->foo();
		}
	}
}


