<?php declare(strict_types=1);

trait myTrait {
	function myTraitFunction(): static {
		return $this;
	}
}

class myClassWithTrait {
	use myTrait;

	function otherFunction(): static {
		return $this;
	}
}

class myClassExtended extends myClassWithTrait {
	function extendedFunction(): static {
		return $this;
	}

	// Test returning result of another method that returns static
	function chainedStaticReturn(): static {
		return $this->extendedFunction();
	}

	// Test returning parent method result
	function returnsParentStatic(): static {
		return $this->otherFunction();
	}

	// Test returning trait method result
	function returnsTraitStatic(): static {
		return $this->myTraitFunction();
	}
}

// Test static return type with static methods
class StaticMethodClass {
	private static ?self $instance = null;

	public static function getInstance(): static {
		if (self::$instance === null) {
			self::$instance = new static();
		}
		return self::$instance;
	}

	public function getSelf(): static {
		return $this;
	}
}

// Test static return type with inheritance and static methods
class ExtendedStaticMethodClass extends StaticMethodClass {
	public function getParentInstance(): static {
		return parent::getInstance();
	}
}

// Test static return type with new static()
class FactoryPattern {
	public static function create(): static {
		return new static();
	}

	public function clone(): static {
		return new static();
	}
}

// Test static return type in abstract class
abstract class AbstractStaticReturn {
	abstract public function getStatic(): static;

	public function returnThis(): static {
		return $this;
	}
}

class ConcreteStaticReturn extends AbstractStaticReturn {
	public function getStatic(): static {
		return $this;
	}
}

// Test static return type in interface
interface StaticReturnInterface {
	public function getStatic(): static;
}

class ImplementsStaticReturn implements StaticReturnInterface {
	public function getStatic(): static {
		return $this;
	}
}