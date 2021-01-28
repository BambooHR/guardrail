<?php namespace BambooHR\Guardrail\Metrics;

Interface MetricOutputInterface {
    function emitMetric(MetricInterface $metric);
}