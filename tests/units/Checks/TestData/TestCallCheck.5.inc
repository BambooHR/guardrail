<?php
function testFunc(string $a) {}

$notTraversable = 123;
testFunc(...$notTraversable);
