<?php
function expectsInt(int $num) {}

function passesArray(array $param) {
	expectsInt($param);
}
