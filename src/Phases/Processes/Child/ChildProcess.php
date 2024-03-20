<?php

namespace BambooHR\Guardrail\Phases\Processes\Child;

use BambooHR\Guardrail\Phases\Processes\Parent\ProcessManager;
use BambooHR\Guardrail\Socket;
use BambooHR\Guardrail\SocketBuffer;

abstract class ChildProcess {
	private \Socket $socket;

	function init(\Socket $socket):void {
		$this->socket=$socket;
	}

	function send(string $message) {
		Socket::writeComplete($this->socket, $message);
	}

	abstract function runCommand(string $command, string ...$params);

	function run():int {
		$buffer = new SocketBuffer();
		while (1) {
			$buffer->read($this->socket);
			foreach ($buffer->getMessages() as $receive) {
				$elements = explode(" ", $receive, 2);
				if (count($elements)==2) {
					list($command, $params) = $elements;
					$params = explode(" ", $params);
				} else {
					$command = $elements[0];
					$params = [];
				}

				if(ProcessManager::CLOSE_CONNECTION == $this->runCommand($command, ...$params)) {
					return ProcessManager::CLOSE_CONNECTION;
				}
			}
		}
	}
}