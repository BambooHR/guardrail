<?php

/**
 * @template T
 * @param class-string<T> $className
 * @return T
 */
function newString(string $className) {
	return new $className();
}

$a = newString(\SplStack::class);
$a->push("Test");
