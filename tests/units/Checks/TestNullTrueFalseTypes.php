<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestNullTrueFalseTypes extends TestSuiteSetup {

	function testFalseParam() {
		$code = <<<'ENDCODE'
		function foo(false $foo):void {
			// WHY?!?!
			var_dump($foo);
		}
		ENDCODE;

		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed false param");
	}

	function testTrueParam() {
		$code = <<<'ENDCODE'
		function foo(true $foo):void {
			// WHY?!?!
			var_dump($foo);
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed true param");
	}

	function testNullParam() {
		$code = <<<'ENDCODE'
		function foo(null $foo):void {
			// WHY?!?!
			var_dump($foo);
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed null param");
	}

	function testFalseReturnValue1() {
		$code = <<<'ENDCODE'
		function foo():false {
			return false;
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed false return type");
	}

	function testFalseReturnValueReturningTrue() {
		$code = <<<'ENDCODE'
		function foo():false {
			return true;
		}
		ENDCODE;
		$this->assertEquals(1, $this->getStringErrorCount($code), "Failed false return type returning true");
	}

	function testNullReturnValue() {
		$code = <<<'ENDCODE'
		function foo():null {
			return null;
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed false return type returning true");
	}

	function testNullReturnNonNull() {
		$code = <<<'ENDCODE'
		function foo():null {
			return null;
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed false return type returning true");
	}

	function testNullableNullNotAllowed() {
		$code = <<<'ENDCODE'
		function foo():?null {
			return null;
		}
		ENDCODE;
		$this->assertEquals(1, $this->getStringErrorCount($code), "Failed false return type returning true");
	}

	function testTrueReturn() {
		$code = <<<'ENDCODE'
		function foo():true {
			return true;
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed false return type returning true");
	}


	function testTrueReturnNonBool() {
		$code = <<<'ENDCODE'
		function foo():true {
			return 5;
		}
		ENDCODE;
		$this->assertEquals(1, $this->getStringErrorCount($code), "Failed false return type returning true");
	}

	function testNeverReturn() {
		$code = <<<'ENDCODE'
		function foo():never {
			exit(1);
		}
		ENDCODE;
		$this->assertEquals(0, $this->getStringErrorCount($code), "Failed false return type returning true");
	}

	function testNeverReturnWithReturn() {
		$code = <<<'ENDCODE'
		function foo():never {
			return 1;
		}
		ENDCODE;
		$this->assertEquals(1, $this->getStringErrorCount($code), "Failed false return type returning true");
	}
}