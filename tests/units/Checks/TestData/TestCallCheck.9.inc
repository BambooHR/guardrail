<?php
function testFunc(string $name, int $age = 0) {}

$callable = testFunc(...);
