<?php
/**
 * isDeprecated
 *
 * @return void
 * @deprecated This is deprecated and should emit
 */
function isDeprecated() {

}

isDeprecated();

class MyNewTestClass {

	/**
	 * myDeprecatedFunction
	 *
	 * @return void
	 * @deprecated
	 */
	public function myDeprecatedFunction() {

	}

	public function myInternalFunction() {
		// calling deprecated
		$this->myDeprecatedFunction();
	}
}

$class = new MyNewTestClass();
$class->myDeprecatedFunction();