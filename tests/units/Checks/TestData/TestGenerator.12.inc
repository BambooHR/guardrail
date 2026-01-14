<?php

class TestClass {
	function method(): self {
		return new self();
	}
}
