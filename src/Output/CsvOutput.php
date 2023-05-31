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
		$this->errors[$fileName][] = ["name"=>$name,"line" => $lineNumber, "message" => $message];
	}

	public function renderResults() {
		$f=fopen("/dev/stdout", "w");
		foreach($this->errors as $fileName=>$errors) {
			usort($errors, function ($cmpa, $cmpb) {
				return $cmpa['line'] > $cmpb['line'] ? 1 : ($cmpa['line'] == $cmpb['line'] ? 0 : -1);
			});
			foreach($errors as $error) {
				fputcsv($f, [$fileName, $error['line'],$error['name'], $error['message']]);
			}
		}
		fclose($f);
	}
}