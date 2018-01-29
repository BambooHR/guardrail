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
		if (!$this->shouldEmit($fileName, $name, $lineNumber)) {
			return;
		}
		if ($this->emitErrors) {
			echo "E";
		}
		$this->errors[$name] = ($this->errors[$name] ?: 0) + 1;
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
			printf("%6d | %s\n", $errors, $className );
		}
	}
}