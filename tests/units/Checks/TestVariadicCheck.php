<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use BambooHR\Guardrail\PhpAstToPhpParser;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestVariadicCheck
 */
class TestVariadicCheck extends TestSuiteSetup {

	/**
	 * testIsVariadicData
	 *
	 * @return void
	 * @dataProvider trueVariadicData
	 * @rapid-unit Checks:VariadicCheck:Valid variadic data passes
	 */
	public function testIsVariadicData($code, $message) {
		$this->assertTrue(VariadicCheckVisitor::isVariadic($this->parseText($code)), $message);
	}

	/**
	 * trueVariadicData
	 *
	 * @return array
	 */
	public function trueVariadicData() {
		return [
			['<?php if(true) { func_get_args(); }', 'Nested call not detected'],
			['<?php func_num_args();', 'Top level func_num_args() call not detected'],
			['<?php func_get_arg(1);', 'Top level func_get_arg() call not detected'],
		];
	}

	/**
	 * This test confirms that nodecontainsVariadicMethodCall will work.  The test only runs in the presence of the
	 * \ast extension.

	 */
	public function testAstIsVariadicData() {
		if (function_exists("\\ast\\parse_code")) {
			$code = '<?php function foo() { if(true) { func_get_args(); } } ?>';
			$parsed = \ast\parse_code($code, 50);
			$this->assertTrue(PhpAstToPhpParser::nodeContainsVariadicMethodCall($parsed[0]), "Nested call not detected");
		}
	}

	/**
	 * testIsNotVariadicData
	 *
	 * @param $code
	 * @param $message
	 *
	 * @return void
	 * @dataProvider falseVariadicData
	 * @rapid-unit Checks:VaridaicData:Invalid variadic data does not pass
	 */
	public function testIsNotVariadicData($code, $message) {
		$this->assertFalse(VariadicCheckVisitor::isVariadic($this->parseText($code)), $message);
	}

	/**
	 * falseVariadicData
	 *
	 * @return array
	 */
	public function falseVariadicData() {
		return [
			['<?php safe_func();', 'Clean code not detected variadic'],
		];
	}



}