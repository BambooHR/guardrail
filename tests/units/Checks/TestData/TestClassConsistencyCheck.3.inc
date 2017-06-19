<?php

trait VarImport {
	public $var;
}

class ParentClass {
	private $var;
}


class TestClassConsistencyCheck3 extends ParentClass {
	use VarImport;
}

