<?php

namespace BambooHR\Guardrail\Output;


class ConsoleOutput extends XUnitOutput {
	private $errors = [];

	public function emitError($className, $fileName, $lineNumber, $name, $message = "") {
		if (!$this->shouldEmit($fileName, $name, $lineNumber)) {
			return;
		}
		if ($this->emitErrors) {
			echo "E";
		}
		$this->errors[$fileName][] = ["line"=>$lineNumber, "message"=>$message];
	}

	public function renderResults() {
		echo "\n";
		foreach($this->errors as $fileName => $errors) {
			echo " Line  | $fileName\n";
			echo "-------+----------------------------------------------------------------\n";
			usort($errors, function($cmpa,$cmpb) {
				return $cmpa['line'] > $cmpb['line'] ? 1 : ($cmpa['line']==$cmpb['line'] ? 0 : -1);
			});
			foreach($errors as $error) {
				printf("%6d | %s\n", $error['line'],$error['message']);
			}
			echo "\n";
		}
	}
}