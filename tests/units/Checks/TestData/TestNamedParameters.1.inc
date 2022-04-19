<?php

namespace BambooHR\Guardrail\Tests\units\Checks\TestData;

class TestNamedParameters {
	static function foo(string $foo, int $bar, string $baz="") {

	}
}

function bar(string $foo, int $bar, string $baz="") {

}


TestNamedParameters::foo(bar:5, foo:"Testing", baz:"bz");

bar("Test", bar:10);



TestNamedParameters::foo(foo:"Testing",1,"bz");

bar(foo:"1", foo:"2", bar: 5);


bar(bat:"2",foo:"1", bar:5);
