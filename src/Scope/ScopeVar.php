<?php

namespace BambooHR\Guardrail\Scope;


use BambooHR\Guardrail\TypeComparer;

class ScopeVar {
	public $name;
	public $used = false;
	public $type;
	public $modified = false;
	public $modifiedLine = 0;
	public $typeChanged = false;

	/**
	 * @param ScopeVar $other -
	 * @return void
	 */
	function mergeVar(ScopeVar $other) {
		$this->typeChanged=true;
		if ($this->type == $other->type) {
			$this->type = TypeComparer::getUniqueTypes($this->type, $other->type);
		}
		if (!$this->modifiedLine && $other->modifiedLine) {
			$this->modifiedLine = $other->modifiedLine;
		}
	}
}