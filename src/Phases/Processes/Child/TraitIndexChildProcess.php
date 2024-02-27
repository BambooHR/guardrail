<?php

namespace BambooHR\Guardrail\Phases\Processes\Child;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Phases\Processes\Parent\ProcessManager;
use BambooHR\Guardrail\SymbolTable;
use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\SymbolTable\PersistantSymbolTable;

class TraitIndexChildProcess extends ChildProcess {
	function __construct(private SymbolTable\SymbolTable $symbolTable) {
	}

	function init(\Socket $socket):void {
		parent::init($socket);
		if ($this->symbolTable instanceof PersistantSymbolTable) {
			$this->symbolTable->connect(0 );
		}
	}

	function runCommand(string $command, string ...$params):int {
		switch($command) {
			case "TRAIT":
				$className = $params[0];
				$class = $this->symbolTable->getClass($className);
				$this->send("TRAITED ".$className." ".base64_encode(serialize(JsonSymbolTable::stripMethodContents($class)))."\n");
				return ProcessManager::READ_CONNECTION;
			case "DONE":
				$this->symbolTable->commit();
				return ProcessManager::CLOSE_CONNECTION;
			default:
				echo "Internal error unknown command: $command\n";
				exit(1);
		}
	}
}