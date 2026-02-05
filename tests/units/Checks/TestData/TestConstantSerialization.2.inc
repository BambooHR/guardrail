<?php

class TestMagicConstant {
	const MY_DIR = __DIR__;

	public function test() {
		// Should recognize MY_DIR as string type
		if (file_exists(self::MY_DIR)) {
			return true;
		}
		return false;
	}
}
