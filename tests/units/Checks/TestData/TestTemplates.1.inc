<?php

/**
 * @template T
 */
class Stack {
	/** @var T[] */
	private array $data=[];

	/** @param T $item */
	function push($item):void {
		array_push($this->data, $item);
	}

	/** @return T */
	function pop() {
		return array_pop($this->data);
	}
}

/** @return Stack<int> */
function newIntStack() {
	return new Stack();
}

$a = newIntStack();
$a->push(new stdclass);

