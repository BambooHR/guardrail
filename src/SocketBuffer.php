<?php

namespace BambooHR\Guardrail;


class SocketBuffer {

	private $buffer = "";
	private $messages = [];

	/**
	 * @param resource $socket Connection to read
	 * @return void
	 */
	function read($socket) {
		$read = "";
		if (socket_recv($socket, $read, 4096, 0) !== false) {
			$this->buffer .= $read;
			$last = 0;
			for ($index = 0; $index < strlen($this->buffer); ++$index) {
				if ($this->buffer[$index] == "\n" || $this->buffer[$index] == "\r") {
					$msg = substr($this->buffer, $last, $index);
					if (trim($msg) != '') {
						$this->messages[] = $msg;
					}
					$last = $index + 1;
				}
			}
			$this->buffer = substr($this->buffer, $last + 1);
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