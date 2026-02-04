<?php

namespace BambooHR\Guardrail\Phases\Processes\Child;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Output\SocketOutput;
use BambooHR\Guardrail\Phases\AnalyzingPhase;
use BambooHR\Guardrail\Phases\Processes\Parent\ProcessManager;

class AnalyzingChildProcess extends ChildProcess {
	function __construct(
		private Config $config,
		private AnalyzingPhase $analyzePhase,
	) {
	}

	function init(\Socket $socket): void {
		parent::init($socket);
		$this->analyzePhase->initParser($this->config, new SocketOutput($this->config, $socket));
	}

	function runCommand(string $command, string ...$params) {
		if ($command == "TIMINGS") {
			$arr = $this->analyzePhase->getAnalyzer()->getTimingsAndCounts();
			$this->send("TIMINGS " . base64_encode(json_encode($arr)) . "\n");
			return ProcessManager::READ_CONNECTION;
		} elseif ($command == "DONE") {
			return ProcessManager::CLOSE_CONNECTION;
		} else {
			$file = $params[0];
			$size = $this->analyzePhase->analyzeFile($file, $this->config);
			$this->send("ANALYZED $size $file\n");
			return ProcessManager::READ_CONNECTION;
		}
	}
}
