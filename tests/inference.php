<?php

class InferenceClass {
	function foo() { }
}

$a=new InferenceClass;
$a->foo(); // Not an error
$a->bar(); // Error

$b=$a;
$b->foo(); // Not an error
$b->bar(); // Error
