<?php

/*
 * Guardrail.  Copyright (c) 2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail;


use BambooHR\Guardrail\Exceptions\SocketException;

/**
 * Class ProcessManager
 *
 * @package BambooHR\Guardrail
 */
class ProcessManager {
	const CLOSE_CONNECTION = 1;
	const READ_CONNECTION = 2;
	private $connections = [];
	/** @var SocketBuffer[] */
	private $buffers = [];

	/**
	 * @param callable $childProcess a closure to run inside the child process.
	 * @return resource
	 */
	function createChild(callable $childProcess) {
		$pair = [];
		if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair)) {
			echo "socket_create_pair failed. Reason: " . socket_strerror(socket_last_error()) . "\n";
		}
		$pid = pcntl_fork();
		if ($pid == -1) {
			// error
		} else if ($pid) {
			$this->connections[$pid] = $pair[1];
			$this->buffers[$pid] = new SocketBuffer();
			socket_close($pair[0]);
			return $pair[1];
		} else {
			socket_close($pair[1]);
			$ret = call_user_func( $childProcess, $pair[0]);
			socket_close($pair[0]);
			exit( $ret );
		}
	}

	function getPidForSocket($socket) {
		return array_search($socket, $this->connections);
	}

	/**
	 * @param callable $serverReadCallBack A closure to run for the server process.
	 * @return void
	 */
	function loopWhileConnections(callable $serverReadCallBack) {
		while (count($this->connections) > 0) {
			$read = $this->connections;
			$none = null;
			$childPid = 0;
			$closeSockets = [];
			do {
				$childPid = pcntl_wait($status, WNOHANG);
				if ($childPid > 0) {
					$closeSockets[] = $childPid;
					if ($status != 0) {
						call_user_func($serverReadCallBack, $this->connections[$childPid], "Child died with non-zero status");
					}
				}
			} while ($childPid > 0);

			if (socket_select($read, $none, $none, null)) {
				foreach ($read as $index => $socket) {
					try {
						$this->buffers[$index]->read($socket);
					}
					catch(SocketException $socketException) {
						echo "Socket error: ".$socketException->getMessage(). "\n";
						unset($this->connections[$index]);
						unset($this->buffers[$index]);
					}
				}
			}
			foreach ($this->buffers as $index => $buffer) {
				$messages = $buffer->getMessages();
				$this->dispatchMessages( $index, $messages, $serverReadCallBack );
			}
			foreach ($closeSockets as $childPid) {
				unset($this->connections[$childPid]);
				unset($this->buffers[$childPid]);
			}
		}
	}

	/**
	 * @param int      $index              The index into the connections array
	 * @param array    $messages           Any messages to transmit
	 * @param callable $serverReadCallBack The callable to pass along to the user function.
	 * @return void
	 */
	function dispatchMessages($index, $messages, $serverReadCallBack) {
		$socket = $this->connections[$index];
		foreach ($messages as $msg) {
			if (trim($msg) !== "" && self::CLOSE_CONNECTION == call_user_func($serverReadCallBack, $socket, $msg)) {
				$childPid = $this->getPidForSocket($socket);
				$status = 0;
				socket_close($socket);
				unset($this->connections[$index]);
				unset($this->buffers[$index]);
				pcntl_waitpid($childPid, $status);
			}
		}
	}
}
