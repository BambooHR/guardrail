<?php

class Foo {
	public ?array $items = ['hello', 'world'];
}

function testFunc(string ...$args) {}

$foo = new Foo();
testFunc(...$foo->items);
