<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\UsageIndex;
use BambooHR\Guardrail\Lsp\SymbolResolver;
use BambooHR\Guardrail\Lsp\Handlers\DefinitionHandler;

echo "=== Testing Namespace Resolution for Go to Definition ===\n\n";

// Load symbol table
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

// Load usage index
$usageIndex = new UsageIndex(__DIR__ . '/method_usage.json', $symbolTable);

// Create symbol resolver
$resolver = new SymbolResolver($symbolTable);

// Create definition handler
$definitionHandler = new DefinitionHandler($resolver, $usageIndex);

echo "1. Testing Direct Class Lookup\n";

// Test looking up ErrorConstants with full namespace
$symbol = $resolver->findSymbol('BambooHR\\Guardrail\\Checks\\ErrorConstants');
if ($symbol) {
    echo "   ✓ Found ErrorConstants with full namespace\n";
    echo "     File: {$symbol['file']}\n";
} else {
    echo "   ✗ ErrorConstants not found with full namespace\n";
}

echo "\n2. Testing Go to Definition on ErrorConstants (line 21 in BaseCheck.php)\n";

$testFile = __DIR__ . '/src/Checks/BaseCheck.php';
$params = (object)[
    'textDocument' => (object)[
        'uri' => 'file:///' . str_replace('\\', '/', $testFile)
    ],
    'position' => (object)[
        'line' => 20, // 0-indexed, so line 21 is index 20
        'character' => 35 // Position on "ErrorConstants"
    ]
];

echo "   - Requesting definition at {$testFile}:21:35\n";
$locations = $definitionHandler->handle($params);

if ($locations && !empty($locations)) {
    echo "   ✓ Found " . count($locations) . " definition(s)\n";
    foreach ($locations as $location) {
        $uri = $location->uri;
        $line = $location->range->start->line + 1;
        echo "     → Line {$line} in " . basename($uri) . "\n";
    }
} else {
    echo "   ✗ No definitions found\n";
}

echo "\n3. Testing Namespace Extraction\n";

$lines = file($testFile);
$reflection = new ReflectionClass($definitionHandler);
$method = $reflection->getMethod('extractNamespace');
$method->setAccessible(true);
$namespace = $method->invoke($definitionHandler, $lines);

echo "   - Extracted namespace: " . ($namespace ?: 'none') . "\n";

echo "\n4. Testing Use Statement Extraction\n";

$method = $reflection->getMethod('extractUseStatements');
$method->setAccessible(true);
$useStatements = $method->invoke($definitionHandler, $lines);

echo "   - Found " . count($useStatements) . " use statements:\n";
foreach ($useStatements as $alias => $fqn) {
    echo "     * {$alias} => {$fqn}\n";
}

echo "\n=== Test Complete ===\n";
