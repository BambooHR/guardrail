<?php

namespace BambooHR\Guardrail;

class DirectoryLister {
	static function getGenerator(string ...$dirs) {
		while (NULL !== ($dir = array_shift($dirs))) {
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