<?php

enum Foo {
	case Bar;
	case Baz;
}

echo Foo::Bar->name;