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
	 * @var bool True if the variable may be null (can be narrowed to false)
	 */
	public $mayBeNull = false;
	
	/**
	 * @var bool True if the variable may be unset (created in one branch but not another)
	 */
	public $mayBeUnset = false;

	/**
	 * @param ScopeVar $other -
	 * @return void
	 */
	function mergeVar(ScopeVar $other) {
		$this->typeChanged = true;
		if ($this->type == $other->type) {
			$this->type = TypeComparer::getUniqueTypes($this->type, $other->type);
		}
		if (!$this->modifiedLine && $other->modifiedLine) {
			$this->modifiedLine = $other->modifiedLine;
		}
		
		// Merge mayBeNull and mayBeUnset flags - if either branch has them, result has them
		$this->mayBeNull = $this->mayBeNull || $other->mayBeNull;
		$this->mayBeUnset = $this->mayBeUnset || $other->mayBeUnset;
	}
}
