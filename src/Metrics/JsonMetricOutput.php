<?php namespace BambooHR\Guardrail\Metrics;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Exceptions\InvalidConfigException;

class JsonMetricOutput implements MetricOutputInterface {
    public function __construct(Config $config) {
        $this->fileHandle = fopen($config->getMetricOutputFile(), "w+");
        if (!$this->fileHandle) {
            throw new InvalidConfigException("Cannot write to the metric file: {$config->getMetricOutputFile()}");
        }
		$this->emitList = $config->getEmitList();
    }

    public function emitMetric(MetricInterface $metric) {
        fwrite($this->fileHandle, json_encode($metric) . "\n");
    }
}