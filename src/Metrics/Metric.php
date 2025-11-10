<?php namespace BambooHR\Guardrail\Metrics;

use JsonSerializable;

class Metric implements MetricInterface {
    private $file;
    private $lineNumber;
    private $type;
    private $data;
    private $causedByTraitData = null;

    /**
     * @param string $file
     * @param string $lineNumber
     * @param string $type
     * @param array  $data
     */
    public function __construct(string $file, string $lineNumber, string $type, array $data) {
        $this->file = $file;
        $this->lineNumber = $lineNumber;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * @param array $traitData
     */
    public function setCausedByTraitData($traitName, $importLine) {
        $this->causedByTraitData = ['name' => $traitName, 'importLine' => $importLine];
    }

    /**
     * @return string
     */
    public function getFile() {
        return $this->file;
    }
 
    /**
     * @return string
     */
    public function getLineNumber() {
        return $this->lineNumber;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return array - maybe becomes MetricDataInterface?
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @return bool
     */
    public function isCausedByTrait() {
        return !is_null($this->causedByTraitData);
    }

    /**
     * @return array
     */
    public function getCausingTraitData() {
        return $this->causedByTraitData;
    }

	#[\ReturnTypeWillChange]
    public function jsonSerialize() {
        $data = [
            'file' => $this->file,
            'lineNumber' => $this->lineNumber,
            'type' => $this->type,
            'data' => $this->data
        ];
        if ($this->isCausedByTrait()) {
            $data['trait'] = $this->causedByTraitData;
        }
        return $data;
    }
}