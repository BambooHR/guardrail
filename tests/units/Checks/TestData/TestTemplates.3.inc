<?php

/**
 * @template T
 * @param T $object
 * @return T
 */
function returnSame($object) {
	return $object;
}


$a = returnSame(new \SplStack);
$a->push("Test");