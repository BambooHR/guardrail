<?php

namespace BambooHR\Guardrail;


class ScopeVar {
	public $name;
	public $used = false;
	public $attributes = 0;
	public $type;
	public $modified = false;
	public $modifiedLine = 0;

	/**
	 * @param ScopeVar $other -
	 * @return void
	 */
	function mergeVar(ScopeVar $other) {
		$this->attributes = Attributes::combine($this->attributes, $other->attributes);
		if ($other->used) {
			$this->used = true;
		}
		if ($other->type != $this->type && $other->type!=Scope::UNDEFINED) {
			$this->type = Scope::MIXED_TYPE;
		}
		if ($other->modified) {
			$this->modified = $other->modified;
			$this->modifiedLine = $other->modifiedLine;
		}
	}
}