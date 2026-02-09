<?php

namespace BambooHR\Guardrail\Checks;

if (!function_exists(__NAMESPACE__ . '\\get_loaded_extensions')) {
	function get_loaded_extensions(): array {
		return array_merge(\get_loaded_extensions(), ['NotRealExtension']);
	}
}

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\DefinedConstantCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Expr\ConstFetch;

/**
 * Class TestDefinedConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestDefinedConstantCheck extends TestSuiteSetup {

	/**
	 * @return void
	 */
	public function testGetCheckNodeTypes() {
		$check = new DefinedConstantCheck(
			new InMemorySymbolTable('/'),
			$this->createMock(OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertIsArray($types);
		$this->assertContains(ConstFetch::class, $types);
	}

	/**
	 * @return void
	 */
	public function testHelperMethodsRecognizeKnownConstants() {
		$check = new DefinedConstantCheck(
			new InMemorySymbolTable('/'),
			$this->createMock(OutputInterface::class)
		);
		$this->assertTrue($check->isLanguageConst('PHP_VERSION'));
		$this->assertTrue($check->isMagicConstant('__LINE__'));
		$this->assertTrue($check->isExtensionConstant('JSON_ERROR_NONE'));
	}

	/**
	 * @return void
	 */
	public function testUndefinedGlobalConstant() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testNamespaceSupport() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testKnownLanguageAndMagicConstantsDoNotEmitErrors() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testKnownLocalAndExtensionConstantsDoNotEmitErrors() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testConstantIsDefinedChecksNamespacedAndGlobal() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testUndefinedGlobalConstantInGlobalNamespace() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testUndefinedGlobalConstantInNamespace() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}

	/**
	 * @return void
	 */
	public function testUndefinedFullyQualifiedGlobalConstantInNamespace() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}
}