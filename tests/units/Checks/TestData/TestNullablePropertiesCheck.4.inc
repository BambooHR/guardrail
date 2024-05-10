<?php

declare(strict_types=1);

function nullError(?int $a):int {
	if (is_null($a)) {
		return 0;
	}
	return $a;
}

nullError(null);
