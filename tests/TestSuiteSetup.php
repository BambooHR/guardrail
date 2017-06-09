<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Output\XUnitOutput;
use BambooHR\Guardrail\Phases\AnalyzingPhase;
use BambooHR\Guardrail\Phases\IndexingPhase;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Class TestSuiteSetup
 *
 * @package BambooHR\Guardrail\Tests
 */
abstract class TestSuiteSetup extends TestCase {

	public $testFile;

	/**
	 * runAnalyzerOnFile
	 *
	 * @param string $fileName
	 * @param mixed  $emit
	 *
	 * @return int
	 */
	public function runAnalyzerOnFile($fileName, $emit) {
		$config = new TestConfig($fileName, $emit);
		$output = new XUnitOutput($config);

		$indexer = new IndexingPhase();
		$indexer->run($config, $output);

		$analyzer = new AnalyzingPhase();
		foreach ($config->config as $listItem) {
			$analyzer->phase2($config, $output, $listItem);
		}
		return $output->getErrorCount();
	}

	/**
	 * Clean up after finished test.
	 *
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();
		unset($this->testFile);

	}

	/**
	 * parseText
	 *
	 * @param string $txt The text to parse
	 *
	 * @return null|\PhpParser\Node[]
	 */
	public function parseText($txt) {
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		return $parser->parse($txt);
	}

	/**
	 * getProtectedMethod
	 *
	 * Call this method to allow access to private / protected methods you need to test.
	 * The results you get back will be the unprotected method.
	 *
	 * @param string $className  The Class::class name for the class
	 * @param string $methodName The protected method we want to test
	 *
	 * @return \ReflectionMethod
	 *
	 * @example $foo = $this->getProtectedMethod(Object::class, 'nameOfMethod');
	 * @example $foo->invokeArgs($foo, ['arg1', 'arg2']);
	 *
	 * @see BenefitTransitionUpdateControllerTest for an example if needed
	 */
	protected function getProtectedMethod($className, $methodName) {
		$class = new \ReflectionClass($className);
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);
		return $method;
	}

	/**
	 * getPrivateMethod
	 *
	 * Just an alias to getProtectedMethod, which will work with private methods, too
	 *
	 * @param string $className  The Class::class name for the class
	 * @param string $methodName The protected method we want to test
	 *
	 * @return \ReflectionMethod
	 */
	protected function getPrivateMethod($className, $methodName) {
		return $this->getProtectedMethod($className, $methodName);
	}

	/**
	 * checkClassEmitsErrorOnce
	 *
	 * @param string $checkClass Name of the CheckClass we are testing
	 * @param Node   $node       The Node we are checking
	 *
	 * @return void
	 */
	public function checkClassEmitsErrorOnce($checkClass, Node $node) {
		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();
		$output->expects($this->once())->method("emitError");
		$emptyTable = new InMemorySymbolTable(__DIR__);
		/** @var BaseCheck $check */
		$check = new $checkClass($emptyTable, $output);
		$check->run(__FILE__, $node, null, null);
	}

	/**
	 * checkClassNeverEmitsError
	 *
	 * @param string $checkClass Name of the CheckClass we are testing
	 * @param Node   $node       The Node we are checking
	 *
	 * @return void
	 */
	public function checkClassNeverEmitsError($checkClass, Node $node) {
		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();
		$output->expects($this->never())->method("emitError");
		$emptyTable = new InMemorySymbolTable(__DIR__);
		$check = new $checkClass($emptyTable, $output);
		$check->run(__FILE__, $node, null, null);
	}

	/**
	 * checkClassEmitsErrorExact
	 *
	 * @param string $checkClass Name of the CheckClass we are testing
	 * @param Node   $node       The Node we are checking
	 * @param int    $times      The number of times we expect the error
	 * @param array  $errorData  The error results
	 *
	 * @return void
	 */
	public function checkClassEmitsErrorExact($checkClass, Node $node, $times, $errorData) {
		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();
		$output->expects($this->exactly((int) $times))->method("emitError");
		call_user_func_array([$output,'withConsecutive'], $errorData);
		$emptyTable = new InMemorySymbolTable(__DIR__);
		$check = new $checkClass($emptyTable, $output);
		$check->run(__FILE__, $node, null, null);
	}
}