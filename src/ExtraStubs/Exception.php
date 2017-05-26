<?php

class Exception {
	function __construct($message="", $code=0, $previous=null) { }
	function getMessage() { }
	function getPrevious() { }
	function getCode() { }
	function getFile() { }
	function getLine() { }
	function getTrace() { }
	function getTraceAsString() { }
	function __toString() {
 return ""; }
}