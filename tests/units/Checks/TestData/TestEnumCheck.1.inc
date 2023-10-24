<?php


trait PropTrait {
	public $foo;
}

enum Foo {
	use PropTrait;
	case Bar;
	case Baz;
}
