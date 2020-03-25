<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Exceptions\SocketException;

class SocketBuffer {

	private $buffer = "";
	private $messages = [];

	static function firstEol($str) {
		$index1 = strpos($str,"\n");
		$index2 = strpos($str,"\r");
		if ($index1 === false && $index2 === false) {
			return false;
		} else if ($index1 === false) {
			return $index2;
		} else if ($index2 === false) {
			return $index1;
		} else {
			return min($index1, $index2);
		}
	}

	/**
	 * @param resource $socket Connection to read
	 * @return void
	 */
	function read($socket) {
		$read = "";
		if (socket_recv($socket, $read, 4096, 0) !== false) {
			$this->buffer .= $read;
			do {
				$index = self::firstEol($this->buffer);
				if ($index !== false) {
					$this->messages[] = trim(substr($this->buffer, 0, $index + 1), "\r\n\0\x0B");
					$this->buffer = substr($this->buffer, $index + 1);
				}
			} while (strlen($this->buffer) > 0 && $index !== false);
		} else {
			throw new SocketException(socket_strerror(socket_last_error($socket)));
		}
	}

	/**
	 * @return array
	 */
	function getMessages() {
		$ret = $this->messages;
		$this->messages = [];
		return $ret;
	}
}