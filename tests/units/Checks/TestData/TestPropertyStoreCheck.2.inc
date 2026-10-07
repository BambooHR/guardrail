<?php declare(strict_types=1);

class Foo {

}

class Bar extends Foo {

}

class Baz {
	public Baz $property;
	public int $intValue;
	public int | null $nullableInt;
}

function test(): mixed {
	return null;
}

$a = new Baz();
$a->property = new Foo();
$a->property = new Bar();
$a->intValue = new Foo();
$a->nullableInt = test();
