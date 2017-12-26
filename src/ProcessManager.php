<?php

/*
 * Guardrail.  Copyright (c) 2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail;


/**
 * Class ProcessManager
 *
 * @package BambooHR\Guardrail
 */
class ProcessManager {
	const CLOSE_CONNECTION = 1;
	const READ_CONNECTION = 2;
	private $connections = [];

	/**
	 * @param string $initialCommand
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
			$this->connections[] = $pair[1];
			socket_close($pair[0]);
			return $pair[1];
		} else {
			socket_close($pair[1]);
			$ret = call_user_func( $childProcess, $pair[0]);
			socket_close($pair[0]);
			exit( $ret );
		}
	}

	/**
	 * @param Config          $config The config
	 * @param OutputInterface $output Output
	 * @param string[]        $list   The files to add
	 * @return void
	 */

	function loopWhileConnections(callable $serverReadCallBack) {
		while (count($this->connections) > 0) {
			$read = $this->connections;
			$none = null;
			if (socket_select($read, $none, $none, null)) {
				foreach ($read as $index => $socket) {
					$msg = trim(socket_read($socket, 200, PHP_NORMAL_READ));
					if (self::CLOSE_CONNECTION == call_user_func($serverReadCallBack, $socket, $msg)) {
						socket_close($socket);
						$status = 0;
						unset($this->connections[$index]);
						pcntl_wait($status);
					}
				}
			}
		}
	}
}
