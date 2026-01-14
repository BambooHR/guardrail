<?php

class TestClass {
	function method(): none {
		// Should pass because it is an empty function, which returns `none` and not a generator
	}
}