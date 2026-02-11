<?php

$flag = true;

if (function_exists('maybe_exists') && $flag) {
	maybe_exists();
}

if ($flag || function_exists('maybe_exists_two')) {
	maybe_exists_two();
}

if (function_exists('maybe_exists_three')) {
	maybe_exists_three();
}
