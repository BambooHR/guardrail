<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;

class Property {
	private $type;
	private $name;
	private $access;
	private $static;

	function __construct($name,$type, $access, $isStatic) {
		$this->name = $name;
		$this->access = $access;
		$this->type = $type;
		$this->static = $isStatic;
	}

	function getName() {
 return $this->name; }
	function getAccess() {
 return $this->access; }
	function getType() {
 return $this->type; }
	function isStatic() {
 return $this->static; }
}