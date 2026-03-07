<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

$st = new JsonSymbolTable('symbol_table.json', __DIR__);
$st->connect(0);

echo "Testing symbol table lookups:\n\n";

$testNames = [
    'ErrorConstants',
    'BambooHR\Guardrail\Checks\ErrorConstants',
    '\BambooHR\Guardrail\Checks\ErrorConstants',
    'bamboohr\guardrail\checks\errorconstants',
];

foreach ($testNames as $name) {
    $file = $st->getClassFile($name);
    echo "getClassFile('{$name}'): " . ($file ?: 'NOT FOUND') . "\n";
}

echo "\nTrying getAbstractedClass:\n";
foreach ($testNames as $name) {
    $class = $st->getAbstractedClass($name);
    echo "getAbstractedClass('{$name}'): " . ($class ? 'FOUND' : 'NOT FOUND') . "\n";
}
