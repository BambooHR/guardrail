<?php

namespace BambooHR\Guardrail\Phases\Processes\Parent;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Exceptions\SocketException;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Phases\AnalyzingPhase;
use BambooHR\Guardrail\Phases\Processes\Child\AnalyzingChildProcess;
use BambooHR\Guardrail\Socket;

class AnalyzingParentProcess extends ProcessManager {
	private int $fileNumber = 0;
	private float $start;

	private int $bytes = 0;

	private int $analyzedCount = 0;

	private $timingResults = [];


	function __construct(
		private array $toProcess,
		private int $totalBytes,
		private OutputInterface $output,
		private MetricOutputInterface $metricOutput
	) {
		$this->start = microtime(true);
	}

	function run(AnalyzingPhase $analyzingPhase, Config $config) {
		for ($this->fileNumber = 0; $this->fileNumber < $config->getProcessCount() && $this->fileNumber < count($this->toProcess); ++$this->fileNumber) {
			$socket = $this->createChild(new AnalyzingChildProcess($config, $analyzingPhase));
			if (!$this->output->isTTY()) {
				$this->output->outputExtraVerbose(sprintf("%d - %s\n", $this->fileNumber, $this->toProcess[$this->fileNumber]));
			}
			Socket::writeComplete($socket, "ANALYZE " . $this->toProcess[$this->fileNumber] . "\n");
		}

		// Server process reports the errors and serves up new files to the list.
		$this->loopWhileConnections();
	}

	function acceptTimings(array $timingResults) {
		list($timings,$counts) = $timingResults;
		foreach ($counts as $name => $count) {
			$this->timingResults[1][$name] = ($this->timingResults[1][$name] ?? 0) + $count;
			$this->timingResults[0][$name] = ($this->timingResults[0][$name] ?? 0) + $timings[$name];
		}
	}
	function getTimings() {
		return $this->timingResults;
	}


	public function displayStatusUpdate(string $analyzedFileName): void {
		$kbs = intval( intdiv($this->bytes, 1024) / (microtime(true) - $this->start) ?: 1.0);
		["total" => $errors, "displayed" => $displayCount] = $this->output->getErrorCounts();
		if ($this->output->isTTY()) {
			$white = $this->output->ttyContent("\33[97m");
			$red = $this->output->ttyContent("\33[31m");
			$reset = $this->output->ttyContent("\33[0m");
			printf("$white%d$reset/$white%d$reset, $white%d$reset/$white%d$reset MB ($white%d$reset%%), $white%d$reset KB/s $red%d$reset errors   \r",
				   $this->analyzedCount, count($this->toProcess),
				   intdiv($this->bytes, 1048576), // 1024x1024
				   intdiv($this->totalBytes, 1048576),
				   intval(round(100 * $this->bytes / $this->totalBytes)),
				   $kbs,
				   $displayCount
			);
		} else {
			$this->output->output(".", sprintf("%d - %s", $this->fileNumber - 1, $analyzedFileName));
		}
	}

	public function handleClientMessage(\Socket $socket, string $message, string ...$params): int {
		$details = ($params[0] ?? "");
		switch ($message) {
			case 'VERBOSE':
				$this->output->outputVerbose($details);
				break;
			case 'EXTRAVERBOSE':
				$this->output->outputExtraVerbose($details);
				break;
			case 'OUTPUT':
				$vars = unserialize($details);
				$this->output->output($vars['v'], $vars['ev']);
				break;
			case 'ERROR' :
				$vars = unserialize(base64_decode($details));

				$this->output->emitError(
					$vars['className'],
					$vars['file'],
					$vars['line'],
					$vars['type'],
					$vars['message']
				);
				break;
			case 'ANALYZED':
				list($size,$analyzedFileName) = $params;
				$this->bytes += intval($size);
				$this->analyzedCount++;
				$this->displayStatusUpdate($analyzedFileName);
				if ($this->fileNumber < count($this->toProcess)) {
					Socket::writeComplete($socket, "ANALYZE " . $this->toProcess[$this->fileNumber] . "\n");
					$this->fileNumber++;
				} else {
					try {
						Socket::writeComplete($socket, "TIMINGS\n");
					} catch (SocketException) {
						$this->output->outputVerbose("Internal protocol error.\n");
						return ProcessManager::CLOSE_CONNECTION;
					}
				}
				break;
			case 'TIMINGS':
				$this->acceptTimings( json_decode(base64_decode($details), true) );
				Socket::writeComplete($socket, "DONE\n");
				return ProcessManager::CLOSE_CONNECTION;

			case 'METRIC':
				$metric = unserialize(base64_decode($details));
				$this->metricOutput->emitMetric($metric);
				break;
			default:
				$this->output->outputVerbose("Internal protocol Error.  Unknown message($message)\n");
				exit(1);
		}
		return ProcessManager::READ_CONNECTION;
	}
}