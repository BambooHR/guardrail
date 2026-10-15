<?php declare(strict_types=1);

class Foo {

}

class Bar extends Foo {

}

class Baz {
	public Foo $property;
	public int $intValue;
	public int | null $nullableInt;
}

function test(): mixed {
	return null;
}

$a = new Baz();
$a->property = new Foo();
$a->property = new Bar();
$a->intValue = 5;
$a->nullableInt = test(); // mixed assigned to int|null