<?php
function function1(array $one, int | bool $two = null) {
	return;
}

function function2(array | string $param) {
	$param2 = true;
	if (!empty($param)) {
		function1($param, $param2);
	}
}