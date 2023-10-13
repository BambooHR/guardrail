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
use ReflectionClass;

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
	 * @param array  $additionalConfig
	 * @return int
	 */
	public function runAnalyzerOnFile($fileName, $emit, array $additionalConfig = []) {
		$output = $this->analyzeFileToOutput($fileName, $emit, $additionalConfig);
		var_dump($output->renderResults());
		return $output->getErrorCount();
	}

	/**
	 * @param string $fileName
	 * @param mixed  $emit
	 * @param array  $additionalConfig
	 * @return XUnitOutput
	 */
	public function analyzeFileToOutput($fileName, $emit, array $additionalConfig = []) {
		$testDataDirectory = $this->getCallerTestDataDirectory($this);
		if (false === strpos($fileName, $testDataDirectory)) {
			$fileName = $testDataDirectory . $fileName;
		}

		if (!file_exists($fileName)) {
			throw new \InvalidArgumentException("That file does not exist. Make sure it follows the NameOfTestClass.#.inc \n pattern and is in the TestData directory of the class file directory.");
		}

		$config = new TestConfig($fileName, $emit, $additionalConfig);
		$output = new XUnitOutput($config);

		$indexer = new IndexingPhase($config);
		$indexer->indexFile($config, $fileName);

		$analyzer = new AnalyzingPhase($output);
		$analyzer->initParser($config, $output);

		$analyzer->analyzeFile($fileName, $config);
		return $output;
	}

	public function analyzeStringToOutput(string $fileName, string $fileData, $emit, array $additionalConfig = []) {
		if (!str_starts_with($fileData,"<?php")) {
			$fileData = "<?php\n".$fileData;
		}
		$config = new TestConfig($fileName, $emit, $additionalConfig);
		$output = new XUnitOutput($config);

		$indexer = new IndexingPhase($config);
		$indexer->indexString($fileName, $fileData);

		$analyzer = new AnalyzingPhase($output);
		$analyzer->initParser($config, $output);
		$analyzer->analyzeString($fileName, $fileData);
		return $output;
	}

	/**
	 * getCallerTestDataDirectory
	 *
	 * @param TestSuiteSetup $callingClass Instance of the TestSuiteSetup (childClass)
	 *
	 * @return string
	 */
	protected function getCallerTestDataDirectory(TestSuiteSetup $callingClass) {
		$class_info = new ReflectionClass($callingClass);
		return dirname($class_info->getFileName()) . '/TestData/' . basename($class_info->getFileName(), '.php');
	}

	/**
	 * Clean up after finished test.
	 *
	 * @return void
	 */
	public function tearDown():void {
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
