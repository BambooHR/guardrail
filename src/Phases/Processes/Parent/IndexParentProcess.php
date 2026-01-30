<?php 

namespace BambooHR\Guardrail\Phases\Processes\Parent;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Socket;

class IndexParentProcess extends ProcessManager {
	private int $fileNumber = 0;
	private int $bytes = 0;
	private float $start;

	private \Iterator $itr;

	function __construct(private Config $config, private OutputInterface $output) {
		$this->start = microtime(true);
	}

	function init(\Iterator $itr):void {
		$this->itr = $itr;
	}

	function displayStatusUpdate(): void {
		if ($this->fileNumber % 50 == 0) {
			$end = microtime(true);
			$process = sprintf(
				"Processing %s%.1f%s KB/second",
				$this->output->ttyContent("\33[97m"),
				$this->bytes / 1024 / ($end - $this->start),
				$this->output->ttyContent("\33[0m")
			);
			if ($this->config->getOutputLevel() == 1) {
				if (!$this->output->isTTY()) {
					$this->output->outputVerbose(".");
				} else {
					$this->output->outputVerbose($process . "   \r");
				}
			} else {
				if ($this->config->getOutputLevel() == 2) {
					$this->output->outputExtraVerbose("\n" . $process . "\n");
				}
			}
		}
	}
	function handleClientMessage(\Socket $socket, $message, string ...$params):int {
		assert( $message == "INDEXED" );

		[$size, $fileName, $childProcessNumber] = $params;
		$this->bytes += intval($size);
		$this->output->outputExtraVerbose(sprintf("%d - %s ($childProcessNumber)\n", ++$this->fileNumber, $fileName));
		$this->displayStatusUpdate();

		if ($this->itr->valid()) {
			Socket::writeComplete($socket, "INDEX " . $this->itr->current() . "\n");
			$this->itr->next();
			return ProcessManager::READ_CONNECTION;
		} else {
			Socket::writeComplete($socket, "DONE\n");
			return ProcessManager::CLOSE_CONNECTION;
		}
	}
}