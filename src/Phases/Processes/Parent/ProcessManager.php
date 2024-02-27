<?php

/*
 * Guardrail.  Copyright (c) 2017-2024 BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail\Phases\Processes\Parent;


use BambooHR\Guardrail\Exceptions\SocketException;
use BambooHR\Guardrail\Phases\Processes\Child\ChildProcess;
use BambooHR\Guardrail\SocketBuffer;

/**
 * Class ProcessManager
 *
 * @package BambooHR\Guardrail
 */
abstract class ProcessManager {
	const CLOSE_CONNECTION = 1;
	const READ_CONNECTION = 2;
	private $connections = [];
	/** @var SocketBuffer[] */
	private $buffers = [];


	/**
	 * @param callable $childProcess a closure to run inside the child process.
	 * @return resource
	 */
	function createChild(ChildProcess $childProcess):\Socket {
		$pair = [];
		if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair)) {
			echo "socket_create_pair failed. Reason: " . socket_strerror(socket_last_error()) . "\n";
		}
		$pid = pcntl_fork();
		if ($pid == -1) {
			// error
		} else if ($pid) {
			// Server side, $pid=client pid
			$this->connections[$pid] = $pair[1];
			$this->buffers[$pid] = new SocketBuffer();
			socket_close($pair[0]);
			return $pair[1];
		} else {
			// Client side
			socket_close($pair[1]);
			$childProcess->init($pair[0]);
			$childProcess->run();
			socket_close($pair[0]);
			exit( 0 );
		}
	}
	function getPidForSocket($socket) {
		return array_search($socket, $this->connections);
	}

	function loopWhileConnections() {
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
						echo "Child died with non-zero status!\n";
						exit($status);
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
				$this->dispatchClientMessages( $index, $messages);
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
	function dispatchClientMessages($index, $messages) {
		$socket = $this->connections[$index];
		foreach ($messages as $msg) {
			if ($msg === false) {
				echo "Error: Unexpected error reading from socket\n";
				exit(1);
			}
			if ($msg === false || (trim($msg) !== "" && self::CLOSE_CONNECTION == $this->dispatchMessage($socket,$msg))) {
				$childPid = $this->getPidForSocket($socket);
				$status = 0;
				socket_close($socket);
				unset($this->connections[$index]);
				unset($this->buffers[$index]);
				pcntl_waitpid($childPid, $status);
			}
		}
	}

	function dispatchMessage(\Socket $socket,$msg):int {
		list($message,$details)=explode(" ",$msg,2);
		return $this->handleClientMessage( $socket, $message, ...explode(" ",$details));
	}

	abstract function handleClientMessage(\Socket $socket, string $message,string ... $params):int;
}
