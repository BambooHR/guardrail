<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

// Usage: php -d phar.readonly=false Build.php

function getPackageMetadata() {
  $baseDir = dirname(dirname(__DIR__));
  $composer = json_decode(
    file_get_contents($baseDir . '/composer.json'),
    true,
    flags: JSON_THROW_ON_ERROR
  );

  return [
    'name' => $composer['name'],
    'version' => $composer['version'],
  ];
}

if (file_exists("guardrail.phar")) {
	unlink("guardrail.phar");
}
try {
	$phar = new Phar('guardrail.phar');
	$metadata = getPackageMetadata();
	$phar->setMetadata($metadata);
	$phar->startBuffering();

	$phar->setDefaultStub('/src/bin/guardrail.php');
	$baseDir = dirname(dirname(__DIR__));
	echo "Building relative to $baseDir\n";
	$it = new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
	$it2 = new class ($it) extends \RecursiveFilterIterator {
		function accept(): bool {
			return $this->current()->getExtension() !== "phar";
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
