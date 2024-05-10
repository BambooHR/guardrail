<?php

declare(strict_types=1);

class NullDefault {
	public ?int $a;

	function init():int {
		if (is_null($this->a)) {
			$this->a = 0;
		}
		return $this->a;
	}
}
