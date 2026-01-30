<?php

namespace BambooHR\Guardrail\Phases\Processes\Child;

use BambooHR\Guardrail\Phases\IndexingPhase;
use BambooHR\Guardrail\Phases\Processes\Parent\ProcessManager;
use BambooHR\Guardrail\SymbolTable\PersistantSymbolTable;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

class IndexChildProcess extends ChildProcess {
	function __construct(private int $processNumber, private SymbolTable $symbolTable, private IndexingPhase $indexingPhase) { }

	function init(\Socket $socket): void {
		parent::init($socket);
		if ($this->symbolTable instanceof PersistantSymbolTable) {
			$this->symbolTable->connect($this->processNumber + 1);
		}
	}

	function runCommand(string $command, string ...$params) {
		switch ($command) {
			case "DONE":
				if ($this->symbolTable instanceof PersistantSymbolTable) {
					$this->symbolTable->flushInserts();
					$this->symbolTable->disconnect();
				}
				return ProcessManager::CLOSE_CONNECTION;
			case "INDEX":
				$file = $params[0];
				$size = $this->indexingPhase->indexFile($file);
				$this->send("INDEXED $size $file " . ($this->processNumber + 1) . "\n");
				return ProcessManager::READ_CONNECTION;
			default:
				echo "Unexpected internal command:  $command\n";
				exit(1);
		}
	}
}