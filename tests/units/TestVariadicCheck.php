<?php
use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use PhpParser\ParserFactory;

class TestVariadicCheck extends \PHPUnit_Framework_TestCase {

	/**
	 * parseText
	 *
	 * @param string $txt The string to parse
	 *
	 * @return null|\PhpParser\Node[]
	 */
	static function parseText($txt) {
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		return $parser->parse($txt);
	}

	/**
	 * testMissingBreak
	 *
	 * @return void
	 */
	public function testMissingBreak() {
		$code = '<?
			if(true) {
				func_get_args();
			}
		';

		$code1 = '<?
			func_num_args();
		';

		$code2= '<?
			func_get_arg(1);
		';

		$code3 = '<?
			safe_func();
		';


		$this->assertTrue(VariadicCheckVisitor::isVariadic(self::parseText($code)), "Nested call not detected");
		$this->assertTrue(VariadicCheckVisitor::isVariadic(self::parseText($code1)),"Top level func_num_args() call not detected");
		$this->assertTrue(VariadicCheckVisitor::isVariadic(self::parseText($code2)),"Top level func_get_arg() call not detected");
		$this->assertFalse(VariadicCheckVisitor::isVariadic(self::parseText($code3)),"Clean code not detected variadic");
	}

}