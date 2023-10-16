<?php namespace BambooHR\Guardrail\Test\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestDefinedConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestUndefinedVariableCheck extends TestSuiteSetup {

	public function testUndefinedVariables() {
		$func = <<<'ENDCODE'
			function method1($one, $two) {
				$test2 = $test + 1;
				return $undefined;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(2, $output->getErrorCount(), "Failed");
	}

	public function testNonExistentVariableInCallback() {
		$func = <<<'ENDCODE'
			function method1($one, $two) {
				$users = [];
				$undefined = [];
				return array_filter($users, function ($user) use ($one) {
					return $undefined;
				});
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Failed");
	}

	public function testNonExistentVariableInUse() {
		$func = <<<'ENDCODE'
			function method1($one, $two) {
				$users = collect([]);
				return $users->map(function ($user) use ($three) {
					return "";
				});
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Failed");
	}

	public function testNestedUseStatementsInGlobalContext() {
		$func = <<<'ENDCODE'
			$company = null;
			return function () use ($company) {
				return function () use ($company) {
					return;
				};
			};
			
			class test {
				function method() {
					$company = null;
					return function () use ($company) {
						return function () use ($company) {
							return;
						};
					};
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testWithException() {
		$func = <<<'ENDCODE'
			class testClass {
				public function method1() {
					try {
						break;
					} catch (\Exception $exception) {
						return;
					}
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testWithForEach() {
		$func = <<<'ENDCODE'
			class testClass {
				public function method1() {
					$tests = [];
					foreach ($tests as $test) {
						break;
					}
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testTryCatchInIfStatment() {
		$func = <<<'ENDCODE'
			function method($one, $two) {
				if ($one && $two) {
					try {
						$three = $two;
					} catch (Throwable $exception) {
					}
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		var_dump($output->renderResults());
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}
	public function testArrayInIf() {
		$func = <<<'ENDCODE'
			private function method($array): array {
				$return = [];
				if ($array) {
					foreach ($array as $item) {
						if (isset($return[$item->id])) {
							$return[$item->id] = $item->name;
						}
					}
				}
				return $return;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}
	public function testLoadList() {
		$func = <<<'ENDCODE'
			function testAssignList($one) {
				list($listOne, $listTwo, $listThree) = $this->loadList($one);
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testUndefinedVariableFile2() {
		$func = <<<'ENDCODE'
			class testClass {
				public function method(string $one, int $two = null, int $three = 0) {
					return collect([])
						->filter(function ($item, $key) use ($two) {
							$test = true || true;
							return true;
						})
						->filter(function ($item) use ($one) {
							return "";
						})
						->map(function($item) use ($three) {
							return "";
						});
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testUndefinedVariableFile3() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_VARIABLE));
	}
}