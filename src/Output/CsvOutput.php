<?php

namespace BambooHR\Guardrail\Output;

class CsvOutput extends XUnitOutput
{
	private $errors = [];

	/**
	 * emitError
	 *
	 * @param string $className  The class name
	 * @param string $fileName   The file name
	 * @param int    $lineNumber The line number
	 * @param string $name       The name
	 * @param string $message    The message
	 *
	 * @return void
	 */
	public function emitError($className, $fileName, $lineNumber, $type, $message = "") {
		$this->totalErrors++;
		if (!$this->shouldEmit($fileName, $type, $lineNumber)) {
			return;
		}
		$this->displayedErrors++;
		$this->errors[$fileName][] = ["name" => $type,"line" => $lineNumber, "message" => $message];
	}

	public function renderResults() {
		if ($this->config->getOutputFile()) {
			$f = fopen($this->config->getOutputFile(), "w");
		} else {
			$f = fopen("php://stdout", "w");
		}
		foreach ($this->errors as $fileName => $errors) {
			usort($errors, fn($cmpa, $cmpb) => $cmpa['line'] <=> $cmpb['line']);
			foreach ($errors as $error) {
				fputcsv($f, [$fileName, $error['line'],$error['name'], $error['message']]);
			}
		}
		fclose($f);
	}
}
