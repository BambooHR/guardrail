<?php

enum Weekday:int {
	case Sunday=1;
	case Monday=2;
	case Tuesday=3;
	case Wednesday =4;
	case Thursday = 5;
	case Friday=6;
	case Saturday = 7;

	function isWeekend():bool {
		return $this->value == 1 || $this->value==7;
	}

	function isWeekDay() : bool {
		return $this->value >=2 && $this->value<=6;
	}
}

$foo=Weekday::from(1);

