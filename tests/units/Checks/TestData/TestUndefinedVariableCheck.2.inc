<?php

class testClass {
	public function method(string $one, int $two = null, int $three = 0) {
		return collect([])
			->filter(function ($item, $key) use ($two) {
				$test = true || true;
				return true;
			})
			->filter(function ($item) use ($one) {
				return "";
			})
			->map(function($item) use ($three) {
				return "";
			});
	}
}