<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

ini_set('xdebug.max_nesting_level', 3000);

// Deals with installation inside /vendor or out.
foreach ([__DIR__ . '/../../../../autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $file) {
	if (file_exists($file)) {
		require $file;
		break;
	}
}
$runner = new CommandLineRunner();
$runner->run($_SERVER["argv"]);
