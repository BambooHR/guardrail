<?php

class TestArrayConstant {
	const MY_ARRAY = [1, 2, 3];

	public function test() {
		// Should recognize MY_ARRAY as array type
		if (in_array(1, self::MY_ARRAY)) {
			return true;
		}
		return false;
	}
}
