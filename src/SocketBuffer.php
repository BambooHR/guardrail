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
		if(socket_recv($socket, $read, 4096, 0)!==false) {
			$this->buffer .= $read;
			$last = 0;
			for ($i = 0; $i < strlen($this->buffer); ++$i) {
				if($this->buffer[$i] == "\n" || $this->buffer[$i] == "\r") {
					$msg = substr($this->buffer, $last, $i);
					if(trim($msg)!='') {
						$this->messages[] = $msg;
					}
					$last = $i+1;
				}
			}
			$this->buffer = substr($this->buffer, $last+1);
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