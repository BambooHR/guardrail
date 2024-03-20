<?php namespace BambooHR\Guardrail\Metrics;

interface MetricOutputInterface {
    function emitMetric(MetricInterface $metric);
}