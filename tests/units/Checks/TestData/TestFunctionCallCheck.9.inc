<?php

class A {}

class B {}


// Not part of the union
class C {}

// D only accepts A or B, not D.
function D(A | B $param): A | B {

}



D(new A());
D(new B());
D(new C());