<?php

class TestClass {
	protected function logException(?Exception $logException = null) {
		if ($logException) {
			$log = [ 'exception' => [
				'exception' => get_class($logException),
				'code' => $logException->getCode(),
				'additionalExceptionInfo' => method_exists($logException, 'getNatureOfError') ? $logException->getNatureOfError() : null, //This should not emit a ErrorConstants::TYPE_UNKNOWN_METHOD
				'file' => $logException->getFile(),
				'line' => $logException->getLine(),
				'trace' => $logException->getTraceAsString(),
				'something' => $logException->something(), //This should emit a ErrorConstants::TYPE_UNKNOWN_METHOD
			]];
		}
		return $log;
	}
}
