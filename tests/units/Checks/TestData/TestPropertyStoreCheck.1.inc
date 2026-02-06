<?php declare(strict_types=1);

class Foo {

}

class Bar extends Foo {

}

class Baz {
	public Foo $property;
	public int $intValue;
	public mixed $mixedValue;
	public int | null $nullableInt;
}

$a = new Baz();
$a->property = new Foo();
$a->property = new Bar();
$a->intValue = 5;
$a->mixedValue = 5;
$a->mixedValue = "hello";
$a->mixedValue = test();
$a->nullableInt = 5;
$a->nullableInt = null;