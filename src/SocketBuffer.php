<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Exceptions\SocketException;

class SocketBuffer {
	private $buffer = "";
	private $messages = [];

	static function firstEol($str) {
		$index1 = strpos($str, "\n");
		$index2 = strpos($str, "\r");
		if ($index1 === false && $index2 === false) {
			return false;
		} elseif ($index1 === false) {
			return $index2;
		} elseif ($index2 === false) {
			return $index1;
		} else {
			return min($index1, $index2);
		}
	}

	/**
	 * @param \Socket $socket Connection to read
	 * @return void
	 */
	function read($socket) {
		$read = "";
		$socketWithActionCount = $this->waitForActionOnSocket($socket);
		if ($socketWithActionCount === 0) {
			return;
		}
		$bytes = socket_recv($socket, $read, 4096, 0);
		$isSocketDisconnected = $bytes === 0;
		$isSocketError = $bytes === false;
		if ($isSocketDisconnected) {
			throw new SocketException("Socket closed unexpectedly.");
		}
		if ($isSocketError) {
			throw new SocketException(socket_strerror(socket_last_error($socket)));
		}
		$this->buffer .= $read;
		do {
			$index = self::firstEol($this->buffer);
			if ($index !== false) {
				$this->messages[] = trim(substr($this->buffer, 0, $index + 1), "\r\n\0\x0B");
				$this->buffer = substr($this->buffer, $index + 1);
			}
		} while (strlen($this->buffer) > 0 && $index !== false);
	}

	/**
	 * waitForActionOnSocket
	 *
	 * When there has been action on a socket, it can either be that bytes are ready to read, or that the connection is closed.
	 * If there is action on a socket, and the action was that the connection is closed, then socket_recv will get 0 bytes when called.
	 *
	 * @param  mixed $socket
	 * @return int
	 */
	protected function waitForActionOnSocket($socket) {
		$readSockets = [$socket];
		$writeSockets = [];
		$exceptSockets = [];
		$results = socket_select($readSockets, $writeSockets, $exceptSockets, null);
		if ($results === false) {
			throw new SocketException(socket_strerror(socket_last_error($socket)));
		}
		return $results;
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
