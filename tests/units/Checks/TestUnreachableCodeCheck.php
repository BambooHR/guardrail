<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestUnreachableCodeCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestUnreachableCodeCheck extends TestSuiteSetup {

	/**
	 * testUnreachableCodeAfterIfConditional
	 *
	 * @return void
	 * @rapid-unit Checks:UnreachableCode:A return inside an if conditional will throw error for unreachable code
	 */
	public function testUnreachableCodeAfterIfConditional() {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNREACHABLE_CODE));
	}

	/**
	 * testUnreachableCodeAfterSwitchConditional
	 *
	 * @return void
	 * @rapid-unit Checks:UnreachableCode:A return inside a switch conditional will throw error for unreachable code
	 */
	public function testUnreachableCodeAfterSwitchConditional() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNREACHABLE_CODE));
	}

	/**
	 * testUnreachableCodeAfterIfConditionalInClassMethod
	 *
	 * @return void
	 * @rapid-unit Checks:UnreachableCode:A return inside an if conditional will throw error for unreachable code in a class method
	 */
	public function testUnreachableCodeAfterIfConditionalInClassMethod() {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNREACHABLE_CODE));
	}

	/**
	 * testUnreachableCodeAfterSwitchConditional
	 *
	 * @return void
	 * @rapid-unit Checks:UnreachableCode:A return inside a switch conditional will throw error for unreachable code in a class method
	 */
	public function testUnreachableCodeAfterSwitchConditionalInClassMethod() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNREACHABLE_CODE));
	}

	public function testThatNonEmptyArrayCanBeUsedAfterEmptyCheck() {
		$func = <<<'ENDCODE'
			class testClass {
				public function method() {
					$array = [];
					if (empty($array)) {
						return;
					}
					foreach ($array as $item) {
						$one = $item;
					}
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}
}