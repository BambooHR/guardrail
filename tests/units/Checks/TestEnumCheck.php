<?php namespace BambooHR\Guardrail\Test\Checks;

use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Checks\EnumCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Stmt\Enum_;

/**
 * Class TestDefinedConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestEnumCheck extends TestSuiteSetup {
	public function testGetCheckNodeTypes() {
		$check = new EnumCheck(
			new InMemorySymbolTable('/'),
			$this->createMock(OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertContains(Enum_::class, $types);
	}

	public function testCasesMethodIsRejected() {
		$enumNode = $this->parseText('<?php enum Foo { case Bar; }')[0];
		$methodNode = $this->parseText('<?php class Foo { public static function cases() {} }')[0]->getMethods()[0];
		$class = $this->createMock(ClassInterface::class);
		$enumNode->stmts = [new ClassMethod($class, $methodNode)];

		$output = $this->analyzeStringToOutput('test.php', '', ErrorConstants::TYPE_ILLEGAL_ENUM, ['basePath' => '/']);
		$check = new EnumCheck(new InMemorySymbolTable('/'), $output);
		$check->run('test.php', $enumNode);
		$this->assertEquals(1, $output->getErrorCount(), 'Failed to detect cases() method in enum');
	}

	public function testInheritance() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_ILLEGAL_ENUM), "Failed to detect inheriting from an enum" );	}

	public function testProperty() {

		$func = <<<'ENDCODE'
			enum Foo {
				case Bar;
				case Baz;
				public $foo;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Failed to detect declaring a property in an enum");

	}

	public function testTraitPropertyFailure() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_ILLEGAL_ENUM), "Failed to detect traits with properties" );
	}


	public function testIncompatibleTypes() {
		$func = <<<'ENDCODE'
			enum Foo:string {
				case Bar="hello";
				case Baz=2;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Failed to detect declaring a property in an enum");
	}

	public function testEmptyBackedTypes() {
		$func = <<<'ENDCODE'
			enum Foo:string {
				case Bar;
				case Baz;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(2, $output->getErrorCount(), "Failed to detect declaring a property in an enum");
	}

	public function testValuesForUnbackedTypes() {
		$func = <<<'ENDCODE'
			enum Foo {
				case Bar=1;
				case Baz="2";
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(2, $output->getErrorCount(), "Failed to detect declaring a property in an enum");
	}

	public function testBackedCallToFrom() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNKNOWN_METHOD), "Failed to find enum::from()" );
	}

	public function testLegalEnumUsage() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_ILLEGAL_ENUM), "Failed to detect traits with properties" );
	}

	public function testValuesTypeFetch() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_UNKNOWN_PROPERTY), "Failed retrieve the name of a specific enum case");
	}
}