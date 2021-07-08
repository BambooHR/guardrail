<?php

class A {}

class B {}

function C(A | B $param) {

}

class D {}

$a = new A();
C($a);
$b = new B();
C($b);
$d = new D();
C($d);