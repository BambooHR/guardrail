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
	 * @param ScopeVar $a
	 * @return void
	 */
	function mergeVar(ScopeVar $a) {
		if ($a->used) {
			$this->used = true;
		}
		if ($a->type != $this->type) {
			if ($a->type == Scope::NULL_TYPE && $this->type != "" && $this->type[0] != '!') {
				$this->canBeNull = Scope::NULL_POSSIBLE;
			} else {
				if ($this->type != "" && $this->type[0] != '!' && $a->type == Scope::NULL_TYPE) {
					$this->canBeNull = true;
					$this->type = $a->type;
				} else {
					$this->type = Scope::MIXED_TYPE;
					$this->canBeNull = Scope::NULL_UNKNOWN;
				}
			}
		} elseif ($a->canBeNull != $this->canBeNull) {
			if ($a->canBeNull == Scope::NULL_POSSIBLE) {
				$this->canBeNull = Scope::NULL_POSSIBLE;
			} else {
				$this->canBeNull = Scope::NULL_UNKNOWN;
			}
		}
		if ($a->modified) {
			$this->modified = $a->modified;
			$this->modifiedLine = $a->modifiedLine;
		}
	}
}