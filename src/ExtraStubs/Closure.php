<?php

class Closure {
	private function __construct() { }
	public static function bind ( Closure $closure , $newthis, $newscope = "static" ) { }
	public function bindTo ( $newthis, $newscope = "static" ) { }
	public function call ( $newthis ) { }
}