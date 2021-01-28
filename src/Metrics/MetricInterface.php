<?php namespace BambooHR\Guardrail\Metrics;

use JsonSerializable;

interface MetricInterface extends JsonSerializable {
    /**
     * @return string
     */
    public function getFile();
    /**
     * @return string
     */
    public function getLineNumber();
    /**
     * @return string
     */
    public function getType();
    /**
     * @return array - maybe becomes MetricDataInterface?
     */
    public function getData();
    /**
     * @return bool
     */
    public function isCausedByTrait();
    /**
     * @return array
     */
    public function getCausingTraitData();
}