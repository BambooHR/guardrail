<?php

class Exception implements Throwable {
	function __construct($message="", $code=0, $previous=null) { }
	function getMessage():string { return ""; }
	function getPrevious() { }
	function getCode() { }
	function getFile():string { return ""; }
	function getLine():int { return 0;}
	function getTrace():array { return []; }
	function getTraceAsString():string { return ""; }
	function __toString() { return ""; }
}