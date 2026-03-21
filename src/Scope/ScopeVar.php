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
	 * @var int Scope version when this variable was defined (0 = inherited from parent)
	 */
	public $scopeVersion = 0;

	/**
	 * Deep clone the variable to ensure type is also cloned
	 */
	public function __clone() {
		// Clone the type if it's an object
		if (is_object($this->type)) {
			$this->type = clone $this->type;
		}
	}

	/**
	 * @param ScopeVar $other -
	 * @return void
	 */
	function mergeVar(ScopeVar $other) {
		$this->typeChanged = true;
		$this->type = TypeComparer::getUniqueTypes($this->type, $other->type);
		
		if (!$this->modifiedLine && $other->modifiedLine) {
			$this->modifiedLine = $other->modifiedLine;
		}
		
		// Merge mayBeNull and mayBeUnset flags - if either branch has them, result has them
		$this->mayBeNull = $this->mayBeNull || $other->mayBeNull;
		$this->mayBeUnset = $this->mayBeUnset || $other->mayBeUnset;
	}
}
