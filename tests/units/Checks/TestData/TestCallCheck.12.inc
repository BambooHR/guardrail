<?php

function testFunc(string ...$args) {}

/** @var ?array $nullableArray */
$nullableArray = ['hello', 'world'];
testFunc(...$nullableArray);