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
			function method1() {
				$users = [];
				//$undefined = [];
				return array_filter($users, function ($user) {
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
				if ($one || $two) {
					try {
						$three = $two;
					} catch (\Throwable $exception) {
						echo $exception->getMessage();
					}
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testArrayInIf() {
		$func = <<<'ENDCODE'
			function method($array): array {
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
				$test = '123';
				[,, $payType, $currencyCode] = $one[0];
				list($listOne, $listTwo, $listThree) = $this->loadList($test);
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNUSED_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(5, $output->getErrorCount(), "Failed");
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

	public function testUndefinedVariable1() {
		$func = <<<'ENDCODE'
			function validateEditCandidate($talentPoolIdsAndReasons): array {
				$errors = [];
				if (is_array($talentPoolIdsAndReasons)) {
					$errors[] = 'TalentPoolIds param must be an array';
				}
				foreach ($talentPoolIdsAndReasons as $reason) {
					if (!($this->length($reason, $reason))) {
						$errors[] = 'Reason is too long';
					}
				}
				return $errors;
			}
			
			function length($reason, $two) {
				return 1;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);

		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testAssigningValuesToUndefinedArray() {
		$func = <<<'ENDCODE'
			function getActions(): ?array {
				$undefined['key'] = 'value';
				$undefined2['key2'] = 'value 2';
				return $undefined;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testPassByReferenceVariables() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_VARIABLE));
	}

	public function testLoadGlobal() {
		$func = <<<'ENDCODE'
			function testMethod($var) {
				global $test;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testStaticProperty() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNKNOWN_VARIABLE));
	}
}