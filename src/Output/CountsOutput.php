<?php

namespace BambooHR\Guardrail\Output;


class CountsOutput extends XUnitOutput {
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
		if (!isset($this->errors[$name])) {
			$this->errors[$name] = 0;
		}
		$this->errors[$name]++;
	}

	/**
	 * @return void
	 */
	public function renderResults() {
		echo "\n";
		echo " Count | Type\n";
		arsort( $this->errors, SORT_NUMERIC );
		echo "-------+----------------------------------------------------------------\n";
		foreach ($this->errors as $className => $errors) {
			if ($this->config->getOutputFile()) {
				file_put_contents($this->config->getOutputFile(), sprintf("%6d | %s\n", $errors, $className), FILE_APPEND);
			} else {
				printf("%6d | %s\n", $errors, $className );
			}
		}
	}
}