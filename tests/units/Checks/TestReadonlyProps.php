<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestReadonlyProps extends TestSuiteSetup {
	public function testConstantReadonly() {
		$func = <<<'ENDCODE'
			class Foo {
				public readonly int $a=5;
			}
			
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_READONLY_DECLARATION, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Assigning a constant into a readonly");
	}

	public function testClassLevelReadonlyConstant() {
		$func = <<<'ENDCODE'
			readonly class  Foo {
				public int $a=5;
			}
			
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_READONLY_DECLARATION, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Assigning a constant into a readonly class");
	}

	public function testUntypedReadonlyClass() {
		$func = <<<'ENDCODE'
			readonly class  Foo {
				public $a;
			}
			
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_READONLY_DECLARATION, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Untyped property in a readonly class");
	}
	public function testUntypedReadonlyProp() {
		$func = <<<'ENDCODE'
			class  Foo {
				readonly public $a;
			}
			
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_READONLY_DECLARATION, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Untyped property in a readonly property");
	}

	public function testValidReadonly() {
		$func = <<<'ENDCODE'
			readonly class Foo {
				public int $a;
				public string $b;
				function __construct() {
					$a=1;
					$b="2";
				}
			}
			
			class Foo2 {
				readonly public int $a;
				readonly public string $b;
			}
			
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_READONLY_DECLARATION, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Untyped property in a readonly property");
	}
}