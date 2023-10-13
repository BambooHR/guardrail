<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


/**
 * Class TestFunctionCalCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestIfBranching extends TestSuiteSetup {

	public function testIfReturn() {

		$func = <<<'ENDCODE'
			function foo(?SplStack $a) {
				if (is_null($a)) {
					return 0;
				}
				return $a->count();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals( 0, $output->getErrorCount(),  "Failed expected no errors" );
	}


	public function testIfNotNull() {
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a) {
				if (!is_null($a)) {
					$a->count();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals( 0, $output->getErrorCount(),  "Failed expected no errors" );
	}



	public function testTernary() {
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a) {
				echo (!is_null($a) ? $a->count() : "Was null");
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals( 0, $output->getErrorCount(),  "Failed expected no errors" );
	}


	public function testIntentionalNullUse() {
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a) {
				echo (is_null($a) ? $a->count() : "Was null");
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals( 1, $output->getErrorCount(),  "Failed expected no errors" );
	}

	public function testCompoundOrIfType() {
		$func = <<<'ENDCODE'
			function foo($a) {
				if ($a instanceof \SplStack || $a instanceof \SplQueue) {
					echo $a->count();
				} 
			}
		ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals( 0, $output->getErrorCount(),  "Failed expected no errors" );
	}


	public function testCompoundAndIfType() {
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a, ?\SplQueue $b) {
				if ($a instanceof \SplStack && $b instanceof \SplQueue) {
					echo $a->count();
					echo $b->count();
				} 
			}
		ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals( 0, $output->getErrorCount(),  "Failed expected no errors" );
	}

	public function testElse() {
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a, ?\SplQueue $b) {
				if (is_null($a)) {
					$a=new \SplStack;
				} else {
					$a=new \SplQueue;
				}
				echo $a->count();
			}
		ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals( 0, $output->getErrorCount(),  "Failed expected no errors" );

	}

	public function testNullOr()
	{
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a) {
				if (is_null($a) || $a->count() == 0) {
					// Ok to call $a->count() on the right, because it isn't null.
				}
			}
ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");
	}

	public function testTernaryNullElse()
	{
		$func = <<<'ENDCODE'
			function foo($recoredYmd=null):string {
				return is_null($recordedYmd) ? $this->getToday()->toSql() : $recordedYmd->toSql();
			}
ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");

	}


	public function testCompoundAndVariableExpression() {
		$func = <<<'ENDCODE'
			function foo(?\DateTimeImmutable $dueDateImmutable, ?\DateTimeImmutable $employeeHireDate) {
				return $dueDateImmutable && $employeeHireDate && $dueDateImmutable->lte($employeeHireDate);
			}
ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");

	}

	public function testCompoundAndExpression() {
		$func = <<<'ENDCODE'
			function foo2(?\DateTimeImmutable $dueDateImmutable, ?\DateTimeImmutable $employeeHireDate) {
				return !is_null($dueDateImmutable) && !is_null($employeeHireDate) && $dueDateImmutable->lte($employeeHireDate);
			}
ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");

	}


	public function testElseIfNulls() {
		$func = <<<'ENDCODE'
			function foo(?\SplStack $a, ?\SplStack $b) {
				if (is_null($a)) {
					echo 0;
				} elseif (is_null($b)) {
					echo $a->count();
				} else {
					echo $a->count();
					echo $b->count();
				}
			}
ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");

	}


	public function testOrIf() {
		$func = <<<'ENDCODE'
		function foo(?\SplStack $a) {
			if (is_null($a) || $a->count() == 0) {
				echo "Emtpy\n";
			}
		}
		ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");

	}


	public function testAndCall() {
		$func = <<<'ENDCODE'
			function foo(\SplStack $a, ?\SplStack $a) {
				!is_null($a) && $b->count();
			}
		ENDCODE;
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed expected no errors");
	}

}
