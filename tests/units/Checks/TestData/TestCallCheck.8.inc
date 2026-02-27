<?php
function testFunc(string $name, int $age = 0) {}

testFunc("John");
testFunc("Jane", 25);
testFunc(name: "Bob", age: 30);
