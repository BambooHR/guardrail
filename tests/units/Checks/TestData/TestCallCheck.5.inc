<?php
function testFunc(string $a, string $b) {}

$notTraversable = 123;
testFunc(...$notTraversable);
