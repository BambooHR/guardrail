<?php

namespace BambooHR\Guardrail\Output;


use BambooHR\Guardrail\Config;

class SocketOutput extends XUnitOutput {
	private $socket;

	/**
	 * SocketOutput constructor.
	 * @param Config   $config The application config
	 * @param resource $socket A connection to a pipe
	 */
	function __construct(Config $config, $socket) {
		parent::__construct($config);
		$this->socket = $socket;
	}

	/**
	 * @param string $className The type of error
	 * @param string $file      The file it occurred in
	 * @param int    $line      The line number it occurred on
	 * @param string $type      The type of error
	 * @param string $message   A human readable message about the error
	 * @return void
	 */
	function emitError(string $className, string $file, int $line, string $type, string $message = "") {
		if ($this->shouldEmit($file, $type, $line)) {
			$arr = [
				"file" => $file,
				"line" => $line,
				"type" => $type,
				"message" => $message,
				"className" => $className
			];
			socket_write($this->socket, "ERROR " . base64_encode(serialize($arr)) . "\n");
		}
	}

	/**
	 * @param string $verbose      The text to show when -v
	 * @param string $extraVerbose The text to show when -v -v
	 * @return void
	 */
	function output($verbose, $extraVerbose) {
		socket_write($this->socket, "OUTPUT " . base64_encode( serialize(["v" => $verbose,"ev" => $extraVerbose]) . "\n"));
	}

	/**
	 * @param string $string What to output
	 * @return void
	 */
	function outputVerbose($string) {
		socket_write($this->socket, "VERBOSE " . base64_encode($string) . "\n");
	}

	/**
	 * @param string $string What to output
	 * @return void
	 */
	function outputExtraVerbose($string) {
		socket_write($this->socket, "EXTRAVERBOSE " . base64_encode($string) . "\n");
	}
}