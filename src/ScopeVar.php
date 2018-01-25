<?php

namespace BambooHR\Guardrail;


class ScopeVar {
	public $name;
	public $used = false;
	public $canBeNull = Scope::NULL_UNKNOWN;
	public $type;
	public $modified = false;
	public $modifiedLine = 0;

	/**
	 * @param ScopeVar $other
	 * @return void
	 */
	function mergeVar(ScopeVar $other) {
		if ($other->used) {
			$this->used = true;
		}
		if ($other->type != $this->type) {
			if ($other->type == Scope::NULL_TYPE && $this->type != "" && $this->type[0] != '!') {
				$this->canBeNull = Scope::NULL_POSSIBLE;
			} else {
				if ($this->type != "" && $this->type[0] != '!' && $other->type == Scope::NULL_TYPE) {
					$this->canBeNull = true;
					$this->type = $other->type;
				} else {
					$this->type = Scope::MIXED_TYPE;
					$this->canBeNull = Scope::NULL_UNKNOWN;
				}
			}
		} elseif ($other->canBeNull != $this->canBeNull) {
			if ($other->canBeNull == Scope::NULL_POSSIBLE) {
				$this->canBeNull = Scope::NULL_POSSIBLE;
			} else {
				$this->canBeNull = Scope::NULL_UNKNOWN;
			}
		}
		if ($other->modified) {
			$this->modified = $other->modified;
			$this->modifiedLine = $other->modifiedLine;
		}
	}
}