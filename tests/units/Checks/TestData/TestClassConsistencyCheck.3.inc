<?php

trait VarImport {
	public $var;
}

class ParentClass {
	private $var;
}


class TestClassConsistencyCheck extends ParentClass {
	use VarImport;
}

