<?php
function testFunc(string &$ref) {}

testFunc("literal" . "expression");
