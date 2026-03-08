<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

// Usage: php -d phar.readonly=false Build.php

if (file_exists("guardrail.phar")) {
	unlink("guardrail.phar");
}
try {
	$phar = new Phar('guardrail.phar');
	$phar->startBuffering();

	$phar->setDefaultStub('/src/bin/guardrail.php');
	$baseDir = dirname(dirname(__DIR__));
	echo "Building relative to $baseDir\n";
	$it = new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
	$it2 = new class ($it) extends \RecursiveFilterIterator {
		function accept(): bool {
			$filename = $this->current()->getFilename();
			$extension = $this->current()->getExtension();
			
			// Skip phar files
			if ($extension === "phar") {
				return false;
			}
			
			// Skip generated JSON files in root
			if ($extension === "json" && dirname($this->current()->getPathname()) === dirname(dirname(__DIR__))) {
				if (in_array($filename, ['metrics.json', 'symbol_table.json', 'method_usage.json'])) {
					return false;
				}
			}
			
			// Skip vim swap files
			if (strpos($filename, '.swp') !== false || strpos($filename, '.swo') !== false) {
				return false;
			}
			
			// Skip git directory
			if ($filename === '.git') {
				return false;
			}
			
			return true;
		}
	};
	$it3 = new \RecursiveIteratorIterator($it2);

	$phar->buildFromIterator($it3, $baseDir);

	$phar->stopBuffering();
	echo "Done\n";
	exit(0);
} catch (Exception $exception) {
	echo "Error building: " . $exception->getMessage() . "\n";
	exit(1);
}
