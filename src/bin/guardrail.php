<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

require_once __DIR__ . '/../../vendor/autoload.php';

$runner=new CommandLineRunner();
$runner->run($_SERVER["argv"]);