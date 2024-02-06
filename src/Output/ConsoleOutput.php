<?php

namespace BambooHR\Guardrail\Output;


class ConsoleOutput extends XUnitOutput {
	/**
	 * @var array
	 */
	private $errors = [];

	/**
	 * @param string $className  -
	 * @param string $fileName   -
	 * @param int    $lineNumber -
	 * @param string $name       -
	 * @param string $message    -
	 * @return void
	 */
	public function emitError($className, $fileName, $lineNumber, $name, $message = "") {
		$this->totalErrors++;
		if (!$this->shouldEmit($fileName, $name, $lineNumber)) {
			return;
		}
		$this->displayedErrors++;
		if ($this->emitErrors && !$this->isTTY()) {
			echo "E";
		}
		$this->errors[$fileName][] = ["line" => $lineNumber, "message" => $message];
	}

	/**
	 * @return void
	 */
	public function renderResults() {
		echo "\n";
		$white=$this->ttyContent("\33[97m");
		$reset=$this->ttyContent("\33[0m");
		foreach ($this->errors as $fileName => $errors) {
			echo " ${white}Line${reset}  | ${white}$fileName${reset}\n";
			echo "-------+----------------------------------------------------------------\n";
			usort($errors, function ($cmpa, $cmpb) {
				return $cmpa['line'] > $cmpb['line'] ? 1 : ($cmpa['line'] == $cmpb['line'] ? 0 : -1);
			});
			foreach ($errors as $error) {
				if (!is_int($error['line'])) {
					var_dump($error);
				}

				printf("%6d | %s\n", $error['line'], $error['message']);
			}
			echo "\n";
		}
	}
}