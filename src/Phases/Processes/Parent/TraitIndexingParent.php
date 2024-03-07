<?php

namespace BambooHR\Guardrail\Phases\Processes\Parent;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Socket;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Phases\Processes\Child\TraitIndexChildProcess;

class TraitIndexingParent extends ProcessManager {

	private $processedFiles = 0;
	private int $fileNumber = 0;

	private float $start;

	function __construct(private array $children, private Config $config, private SymbolTable $symbolTable, private OutputInterface $output) {}

	function initChildren() {
		$processCount = min($this->config->getProcessCount(), count($this->children));

		for($this->fileNumber = 0; $this->fileNumber < $processCount; ++$this->fileNumber) {
			$socket = $this->createChild(new TraitIndexChildProcess($this->symbolTable));
			Socket::writeComplete($socket, "TRAIT ".$this->children[$this->fileNumber]."\n");
			$this->output->outputExtraVerbose(($this->fileNumber+1) .":".$this->children[$this->fileNumber]."\n");
		}
	}

	function run() {
		$this->output->outputVerbose("\n\nImporting & reindexing ".count($this->children)." classes that use a trait\n");
		$this->start=microtime(true);
		$classes = $this->symbolTable->getClassesThatUseAnyTrait();
		$this->symbolTable->begin();
		$this->initChildren();
		$this->loopWhileConnections();
		$this->symbolTable->commit();
		$this->showStatus();
		$this->output->outputVerbose("\nDone\n");

		$this->output->outputVerbose(sprintf("Took %.1f seconds to import traits into %d classes\n", microtime(true)-$this->start, count($classes)));
	}

	function handleClientMessage(\Socket $socket, string $message, string ...$params): int {
		assert($message == "TRAITED");
		$this->output->outputExtraVerbose("Updating class ".$params[0]. " with ".strlen($params[1])." bytes of data\n");
		$class=unserialize(base64_decode($params[1]));
		$this->symbolTable->updateClass($class);
		$this->processedFiles++;
		$this->showStatus();
		if ($this->fileNumber < count($this->children)) {
			$this->output->outputExtraVerbose( ($this->fileNumber+1).":".$this->children[$this->fileNumber]."\n");
			Socket::writeComplete($socket, "TRAIT " . $this->children[$this->fileNumber]."\n" );
			$this->fileNumber++;
			return ProcessManager::READ_CONNECTION;
		} else {
			Socket::writeComplete($socket, "DONE\n");
			return ProcessManager::CLOSE_CONNECTION;
		}
	}
	function showStatus() {
		if ($this->output->isTTY()) {
			if (count($this->children)>0) {
				$this->output->outputVerbose(
					sprintf("Indexing used traits %d/%d %d%%\r",
							$this->processedFiles,
							count($this->children),
							round(100 * $this->processedFiles / count($this->children))
					)
				);
			}
		}
	}

}