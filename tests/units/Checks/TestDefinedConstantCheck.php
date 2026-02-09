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
	public function testConstantIsDefinedChecksNamespacedAndGlobal() {
		$symbolTable = new InMemorySymbolTable('/');
		$symbolTable->addDefine('MyConst', $this->parseText('<?php define("MyConst", 1);')[0], 'file.php');
		$symbolTable->addDefine('Space1\\MyConst', $this->parseText('<?php namespace Space1; const MyConst = 1;')[0], 'file.php');
		$check = new DefinedConstantCheck(
			$symbolTable,
			$this->createMock(OutputInterface::class)
		);
		$method = $this->getProtectedMethod(DefinedConstantCheck::class, 'constantIsDefined');
		$this->assertTrue($method->invoke($check, 'Space1\\MyConst', 'MyConst'));
		$this->assertTrue($method->invoke($check, '', 'MyConst'));
	}

	/**
	 * testUndefinedGlobalConstant
	 *
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
	public function testKnownConstantsDoNotEmitErrors() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}
}