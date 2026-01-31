<?php

namespace BambooHR\Guardrail;

class DirectoryLister {
	/**
	 * @param string ...$dirs
	 *
	 * @return \Generator
	 * @guardrail-ignore Standard.ConditionalAssignment
	 */
	static function getGenerator(string ...$dirs) {
		while (null !== ($dir = array_shift($dirs))) {
			if ($dh = opendir($dir)) {
				while (false !== ($file = readdir($dh))) {
					if ($file != '.' && $file != '..') {
						$path = $dir . DIRECTORY_SEPARATOR . $file;
						if (is_dir($path)) {
							$dirs[] = $path;
						} else {
							yield $path;
						}
					}
				}
				closedir($dh);
			}
		}
	}
}
