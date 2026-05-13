<?php declare(strict_types=1);
use ThrowHelper;

function functionCallingThrowFunction(): int {
	ThrowHelper::throwFunction();
}
