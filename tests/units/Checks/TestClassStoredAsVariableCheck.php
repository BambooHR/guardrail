<?php namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Checks\ClassStoredAsVariableCheck;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Node\Scalar\String_;

/**
 * Class TestClassStoredAsVariableCheck
 *
 * Note: ClassStoredAsVariableCheck is commented out in StaticAnalyzer.php, so we load it
 * via the plugin system for testing purposes.
 *
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestClassStoredAsVariableCheck extends TestSuiteSetup {

	private function getPluginConfig(): array {
		return [
			'plugins' => [__DIR__ . '/TestData/ClassStoredAsVariableCheckPlugin.php'],
		];
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Detects class name stored as string within the same class
	 */
	public function testDefinedClassStoredAsVariable() {
		$this->assertEquals(
			2,
			$this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Detects class name stored as string referencing another class
	 */
	public function testClassStoredAsVariableReferencingOtherClass() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Detects short class name in namespaced file via classExistsAnyNamespace
	 */
	public function testNamespacedClassWithShortName() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Detects short class name referencing class in same namespace
	 */
	public function testNamespacedClassReferencingOtherClassShortName() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Does not emit error for non-existent class name
	 */
	public function testNonExistentClassNameNoError() {
		$this->assertEquals(
			0,
			$this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Does not emit error for non-existent class name in namespaced file
	 */
	public function testNamespacedNonExistentClassNameNoError() {
		$this->assertEquals(
			0,
			$this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	/**
	 * @rapid-unit Checks:ClassStoredAsVariableCheck:Does not emit error for FQCN with backslashes (invalid class name pattern)
	 */
	public function testFqcnWithBackslashesIgnored() {
		$this->assertEquals(
			0,
			$this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE, $this->getPluginConfig())
		);
	}

	public function testGetCheckNodeTypes() {
		$check = new ClassStoredAsVariableCheck(
			$this->createMock(SymbolTable::class),
			$this->createMock(OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertIsArray($types);
		$this->assertContains(String_::class, $types);
	}
}