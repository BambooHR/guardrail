<?php

class TestClass {
	function method(): \Generator {
		yield from [1, 2, 3];
		return [4, 5, 6];
	}
}
