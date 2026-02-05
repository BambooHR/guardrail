<?php

function newFunction($here) {
	function newDeclared() {
		// this function should emit error
		function anotherNestedFunction() {
			// this should also emit
		}
	}
}

function anotherFunction() {
	foreach ($var as $that) {
		function someNewFunction() {
		}
	}
}
