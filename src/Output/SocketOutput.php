<?php

namespace BambooHR\Guardrail\Output;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Metrics\MetricInterface;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Socket;

class SocketOutput extends XUnitOutput implements MetricOutputInterface {
	private \Socket $socket;

	/**
	 * SocketOutput constructor.
	 * @param Config  $config The application config
	 * @param \Socket $socket A connection to a pipe
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
	function emitError($className, $file, $line, $type, $message = "") {
		if (($this->silenced[$type] ?? 0) > 0) {
			return;
		}
		$arr = [
			"file" => $file,
			"line" => $line,
			"type" => $type,
			"message" => $message,
			"className" => $className
		];
		Socket::writeComplete($this->socket, "ERROR " . base64_encode(serialize($arr)) . "\n");
	}

	public function emitMetric(MetricInterface $metric): void {
		if ($this->shouldEmit($metric->getFile(), $metric->getType(), $metric->getLineNumber())) {
			Socket::writeComplete($this->socket, "METRIC " . base64_encode(serialize($metric)) . "\n");
		}
	}

	/**
	 * @param string $verbose      The text to show when -v
	 * @param string $extraVerbose The text to show when -v -v
	 * @return void
	 */
	function output($verbose, $extraVerbose) {
		Socket::writeComplete($this->socket, "OUTPUT " . base64_encode(serialize(["v" => $verbose,"ev" => $extraVerbose]) . "\n"));
	}

	/**
	 * @param string $string What to output
	 * @return void
	 */
	function outputVerbose($string) {
		Socket::writeComplete($this->socket, "VERBOSE " . base64_encode($string) . "\n");
	}

	/**
	 * @param string $string What to output
	 * @return void
	 */
	function outputExtraVerbose($string) {
		Socket::writeComplete($this->socket, "EXTRAVERBOSE " . base64_encode($string) . "\n");
	}
}