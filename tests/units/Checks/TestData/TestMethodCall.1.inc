<?php

/**
 * Class TestMethodCallObject1
 */
class TestMethodCallObject1 {
	/**
	 * getObject2
	 *
	 * @param bool $getit
	 *
	 * @return null|TestMethodCallObject2
	 * @note: when removing the ?TestMethodCallObject2 return type hint, it doesn't seem to fail.
	 */
	public function getObject2(bool $getit): ?TestMethodCallObject2 {
		return $getit ? new TestMethodCallObject2() : null;
	}
}

class TestMethodCallObject2 {
	public function get() {
		return true;
	}
}

$obj1 = new TestMethodCallObject1();
$obj2 = $obj1->getObject2(true);

//currently fails
if (empty($obj2) || $obj2->get()) {
	//do stuff
}

//currently succeeds
if (empty($obj2) || ($obj2 && $obj2->get())) {
	//do stuff
}