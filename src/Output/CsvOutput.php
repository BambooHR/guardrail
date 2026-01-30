<?php

namespace BambooHR\Guardrail\Output;

class CsvOutput extends XUnitOutput
{
	private $errors = [];

	public function emitError($className, $fileName, $lineNumber, $name, $message = "") {
		$this->totalErrors++;
		if (!$this->shouldEmit($fileName, $name, $lineNumber)) {
			return;
		}
		$this->displayedErrors++;
		$this->errors[$fileName][] = ["name" => $name,"line" => $lineNumber, "message" => $message];
	}

	public function renderResults() {
		if ($this->config->getOutputFile()) {
			$f = fopen($this->config->getOutputFile(), "w");
		} else {
			$f = fopen("php://stdout", "w");
		}
		foreach ($this->errors as $fileName => $errors) {
			usort($errors, fn($cmpa, $cmpb) => $cmpa['line'] <=> $cmpb['line'] );
			foreach ($errors as $error) {
				fputcsv($f, [$fileName, $error['line'],$error['name'], $error['message']]);
			}
		}
		fclose($f);
	}
}