<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

/* PHP arrays have a dual nature, they have an implicit order of insertion
 * and they have efficient key=>value lookup.  We leverage this here to
 * make an efficient cache.  The least recently used item will expire from the
 * cache whenever we try to add a new item that would otherwise cause us to exceed
 * the cache size.
 *
 */
class ObjectCache {

	private $objects;
	private $objectCount = 0;
	private $maxObjects = 0;

	function __construct($size=500) {
		$this->objects = [];
		$this->objectCount = 0;
		$this->maxObjects = $size;
	}

	function add($key,$value) {
		if (!isset($this->objects[$key])) {
			if ($this->objectCount == $this->maxObjects) {
				$this->removeLru();
			}
			$this->objectCount++;
		} else {
			unset($this->objects[$key]);
		}
		$this->objects[$key] = $value;
	}

	function get($key) {
		static $hits = 0;
		static $misses = 0;

		if (isset($this->objects[$key])) {
			$value = $this->objects[$key];
			// Remove the key from the current position, and re-add it as the newest item.
			unset($this->objects[$key]);
			$this->objects[$key] = $value;
			$hits++;
			//printf(" %.1f ($hits/$misses)", $hits/($hits+$misses)*100);
			return $value;
		}
		//printf(" %.1f", $hits/($hits+$misses));
		$misses++;
		return null;
	}

	function removeLru() {
		array_shift($this->objects);
		--$this->objectCount;
	}
}