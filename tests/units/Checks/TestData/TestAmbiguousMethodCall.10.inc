<?php

interface BaseInterface {
	public function method();
}

abstract class AbstractBase implements BaseInterface {
	abstract public function method();
}

class ConcreteA extends AbstractBase {
	public function method() {}
}

class ConcreteB extends AbstractBase {
	public function method() {}
}

function test(ConcreteA|ConcreteB $obj) {
	return $obj->method();
}
