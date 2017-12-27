<?php

namespace BambooHR\Guardrail\Output;


use BambooHR\Guardrail\Config;

class SocketOutput extends XUnitOutput {
	private $socket;

	function __construct(Config $config, $socket) {
		parent::__construct($config);
		$this->socket = $socket;
	}

	function emitError($className, $file, $line, $type, $message = "") {
		if ($this->shouldEmit($file, $type, $line)) {
			$arr = ["file"=>$file, "line"=>$line, "type"=>$type, "message"=>$message,"className"=>$className];
			socket_write($this->socket, "ERROR ".base64_encode(serialize($arr))."\n");
		}
	}

	function output($verbose, $extraVerbose) {
		// TODO: Implement output() method.
		socket_write($this->socket,"OUTPUT ".base64_encode( serialize(["v"=>$verbose,"ev"=>$extraVerbose])."\n"));
	}

	function outputVerbose($string) {
		socket_write($this->socket,"VERBOSE ".base64_encode($string)."\n");
	}

	function outputExtraVerbose($string) {
		socket_write($this->socket,"EXTRAVERBOSE ".base64_encode($string)."\n");
	}
}