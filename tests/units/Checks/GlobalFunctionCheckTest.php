<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\GlobalFunctionCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;

/**
 * Class GlobalFunctionCheckTest
 *
 * Tests for the GlobalFunctionCheck class, which prohibits functions at the global level
 * while allowing them in namespaces, classes, or conditional blocks.
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class GlobalFunctionCheckTest extends TestSuiteSetup {

    /**
     * Provides test cases for the GlobalFunctionCheck
     *
     * @return array Array of test cases with [description, file, expected error count]
     */
    public static function functionLocationProvider(): array {
        return [
            'global function' => [
                'Function at the global level should be flagged',
                '.1.inc',
                1
            ],
            'class method' => [
                'Method inside a class should be allowed',
                '.2.inc',
                0
            ],
            'simple conditional function' => [
                'Function inside a simple conditional block should be allowed',
                '.3.inc',
                0
            ],
            'namespaced function' => [
                'Function inside a namespace should be allowed',
                '.4.inc',
                0
            ],
            'complex conditional function' => [
                'Function inside a complex conditional block should be allowed',
                '.5.inc',
                0
            ],
            'elseif/else conditional function' => [
                'Function inside an elseif/else block should be allowed',
                '.6.inc',
                0
            ],
        ];
    }

    /**
     * Test function locations
     *
     * @dataProvider functionLocationProvider
     * @param string $description Test case description
     * @param string $file Test file to analyze
     * @param int $expectedErrorCount Expected number of errors
     * @return void
     * @rapid-unit Checks:GlobalFunctionCheck:Validates function locations
     */
    public function testFunctionLocations(string $description, string $file, int $expectedErrorCount) {
        $this->assertEquals($expectedErrorCount, $this->runAnalyzerOnFile($file, ErrorConstants::TYPE_GLOBAL_FUNCTION), $description);
    }

	/**
	 * @return void
	 * @rapid-unit Checks:GlobalFunctionCheck:Ignores non-function nodes
	 */
	public function testIgnoresNonFunctionNodes(): void {
		$node = new Class_(new Identifier('DemoClass'));
		$this->checkClassNeverEmitsError(GlobalFunctionCheck::class, $node);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:GlobalFunctionCheck:Ignores functions when inside a class
	 */
	public function testIgnoresFunctionsInsideClasses(): void {
		$node = new Function_('demoFunction');
		$inside = new Class_(new Identifier('DemoClass'));

		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->onlyMethods(["emitError"])
			->getMockForAbstractClass();
		$output->expects($this->never())->method("emitError");
		$emptyTable = new InMemorySymbolTable(__DIR__);
		$check = new GlobalFunctionCheck($emptyTable, $output);
		$check->run(__FILE__, $node, $inside, null);
	}


}
