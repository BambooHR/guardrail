<?php

class Foo {

}

class Bar extends Foo {

}

class Baz {
	public Baz $property;
	public int $intValue;
}

$a = new Baz();
$a->property = new Foo();
$a->property = new Bar();
$a->intValue = new Foo();