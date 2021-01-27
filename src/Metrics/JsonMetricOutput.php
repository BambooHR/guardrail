<?php namespace BambooHR\Guardrail\Metrics;

class JsonMetricOutput implements MetricOutputInterface {
    public function __construct($filename) {
        $this->fileHandle = fopen($filename, "w+");
        if (!$this->fileHandle) {

        }
    }

    public function emitMetric(MetricInterface $metric) {
        fwrite($this->fileHandle, json_encode($metric) . "\n");
    }
}