<?php

class Exception {
	function __construct($message="", $code=0, $previous=NULL) { }
	function getMessage() { }
	function getPrevious() { }
	function getCode() { }
	function getFile() { }
	function getLine() { }
	function getTrace() { }
	function getTraceAsString() { }
}