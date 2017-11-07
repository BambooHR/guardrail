<?php



class Test {
	// Complexity = 3
	function factorial($num) {
		if ($num < 0) {
			throw new InvalidArgumentException();
		} else {
			if ($num >= 1) {
				return $num * factorial($num - 1);
			} else {
				return 1;
			}
		}
	}

	// Complexity == 26
	function isAlpha($char) {
		return $char == 'a' || $char == 'b' || $char == 'c' || $char == 'd' || $char == 'e' || $char == 'f' ||
			$char == 'g' || $char == 'h' || $char == 'i' || $char == 'j' || $char=='k' || $char=='l' || $char=='m' ||
			$char == 'n' || $char=='o' || $char=='p' || $char=='q' || $char=='s' ||$char=='t' ||$char=='u' ||
			$char =='v' || $char=='w' || $char =='x' ||$char=='y' ||$char=='z';
	}
}