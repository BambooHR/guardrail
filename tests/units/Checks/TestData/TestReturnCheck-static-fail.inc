<?php declare(strict_types=1);

// Test returning wrong type instead of static
class WrongReturnType {
	public function returnsString(): static {
		return "string"; // Should fail - returning string instead of static
	}

	public function returnsInt(): static {
		return 42; // Should fail - returning int instead of static
	}

	public function returnsNull(): static {
		return null; // Should fail - returning null instead of static
	}

	public function returnsArray(): static {
		return []; // Should fail - returning array instead of static
	}
}

// Test returning instance of different class
class OtherClass {
}

class ReturnsWrongClass {
	public function returnsOtherClass(): static {
		return new OtherClass(); // Should fail - returning wrong class
	}
}

// Test returning parent class when static is expected
class ParentClass {
}

class ChildClass extends ParentClass {
	public function returnsParent(): static {
		return new ParentClass(); // Should fail - parent is not static
	}
}

// Test static method returning wrong type
class StaticMethodWrongReturn {
	public static function create(): static {
		return "not an instance"; // Should fail
	}
}

// Test missing return statement
class MissingReturn {
	public function noReturn(): static {
		// Should fail - no return statement
		$x = 5;
	}

	public function conditionalMissingReturn(bool $flag): static {
		if ($flag) {
			return $this;
		}
		// Should fail - missing return in else branch
	}
}

// Test returning void when static is expected
class ReturnsVoid {
	public function voidReturn(): static {
		return; // Should fail - returning void when static is expected
	}
}

// Test trait returning wrong type
trait WrongReturnTrait {
	public function traitWrongReturn(): static {
		return 123; // Should fail
	}
}

class UsesWrongReturnTrait {
	use WrongReturnTrait;
}

// Test interface implementation with wrong return
interface StaticReturnInterface {
	public function getStatic(): static;
}

class WrongImplementation implements StaticReturnInterface {
	public function getStatic(): static {
		return false; // Should fail
	}
}

// Test abstract class with wrong concrete implementation
abstract class AbstractStaticReturn {
	abstract public function getStatic(): static;
}

class WrongConcreteReturn extends AbstractStaticReturn {
	public function getStatic(): static {
		return "wrong"; // Should fail
	}
}
