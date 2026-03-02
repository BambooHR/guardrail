<?php
function testFunc(string $a) {}

$traversable = ['hello'];
testFunc(...$traversable);
