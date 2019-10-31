<?php

class Foo {

}

class Bar extends Foo {

}

class Baz {
	public Foo $property;
	public int $intValue;
}

$a = new Baz();
$a->property = new Foo();
$a->property = new Bar();
$a->intValue = 5;