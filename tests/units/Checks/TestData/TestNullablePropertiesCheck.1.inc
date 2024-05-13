<?php

namespace BambooHR\Guardrail\Tests\units\Checks\TestData;

class Container {
	private ?\SplStack $stack;

	private function testPropertyIsNotNull(?Container $param) {
		if (isset($param->stack)) {
			// Note: $param can't be null, because it's member isn't null.
			$count = $param->stack->count();
		}
	}
}


