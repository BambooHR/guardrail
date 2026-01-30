<?php 

namespace BambooHR\Guardrail\Metrics;

class JsonMetricOutput implements MetricOutputInterface {
	private $fileHandle = null;

	public function __construct(private string $filename) {
	}

	public function emitMetric(MetricInterface $metric): void {
		if (!is_resource($this->fileHandle)) {
			$this->fileHandle = fopen($this->filename, "w");
		}
		fwrite($this->fileHandle, json_encode($metric) . "\n");
	}

	public function close(): void {
		if (is_resource($this->fileHandle)) {
			fclose($this->fileHandle);
			$this->fileHandle = null;
		}
	}
}