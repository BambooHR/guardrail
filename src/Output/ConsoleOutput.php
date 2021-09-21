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
	public function emitError(string $className, string $fileName, int $lineNumber, string $name, string $message = "") {
		if (!$this->shouldEmit($fileName, $name, $lineNumber)) {
			return;
		}
		if ($this->emitErrors) {
			echo "E";
		}
		$this->errors[$fileName][] = ["line" => $lineNumber, "message" => $message];
	}

	/**
	 * @return void
	 */
	public function renderResults() {
		echo "\n";
		foreach ($this->errors as $fileName => $errors) {
			echo " Line  | $fileName\n";
			echo "-------+----------------------------------------------------------------\n";
			usort($errors, function ($cmpa, $cmpb) {
				return $cmpa['line'] > $cmpb['line'] ? 1 : ($cmpa['line'] == $cmpb['line'] ? 0 : -1);
			});
			foreach ($errors as $error) {
				printf("%6d | %s\n", $error['line'], $error['message']);
			}
			echo "\n";
		}
	}
}