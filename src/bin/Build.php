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
	$it2 = new \RecursiveIteratorIterator($it);

	$phar->buildFromIterator($it2, $baseDir);

	$phar->stopBuffering();
	echo "Done\n";
	exit(0);
} catch (Exception $exception) {
	echo "Error building: " . $exception->getMessage() . "\n";
	exit(1);
}

