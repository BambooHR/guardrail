<?php

class Foo {
	function __construct(
		public int $a,
		private string $b
	) {
	}
}