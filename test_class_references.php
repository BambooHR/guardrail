<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\UsageIndex;
use BambooHR\Guardrail\Lsp\SymbolResolver;
use BambooHR\Guardrail\Lsp\Handlers\DefinitionHandler;

echo "=== Testing Class Reference Indexing for Go to Definition ===\n\n";

// Load symbol table
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

// Load usage index
$usageIndex = new UsageIndex(__DIR__ . '/method_usage.json', $symbolTable);

// Create symbol resolver
$resolver = new SymbolResolver($symbolTable);

// Create definition handler
$definitionHandler = new DefinitionHandler($resolver, $usageIndex);

echo "1. Testing UsageIndex - Class Reference Loading\n";
echo "   - Checking if class references were loaded...\n";

// Test getting a class reference at a specific position
// We'll need to check the method_usage.json to see if class references are there
$testFile = __DIR__ . '/src/Checks/BaseCheck.php';
$testLine = 54; // Line where there might be a class reference

$classRef = $usageIndex->getClassReferenceAtPosition($testFile, $testLine);
if ($classRef) {
    echo "   ✓ Found class reference at line {$testLine}: {$classRef['class_name']}\n";
} else {
    echo "   ✗ No class reference found at line {$testLine}\n";
    echo "   Note: This is expected if the index hasn't been regenerated yet\n";
}

echo "\n2. Testing DefinitionHandler - Go to Definition on Class Names\n";

// Simulate a Go to Definition request on a class name
$params = (object)[
    'textDocument' => (object)[
        'uri' => 'file:///' . str_replace('\\', '/', $testFile)
    ],
    'position' => (object)[
        'line' => $testLine - 1, // 0-indexed for LSP
        'character' => 10
    ]
];

echo "   - Requesting definition at {$testFile}:{$testLine}\n";
$locations = $definitionHandler->handle($params);

if ($locations && !empty($locations)) {
    echo "   ✓ Found " . count($locations) . " definition(s)\n";
    foreach ($locations as $location) {
        $uri = $location->uri;
        $line = $location->range->start->line + 1; // Convert back to 1-indexed
        echo "     → {$uri}:{$line}\n";
    }
} else {
    echo "   ✗ No definitions found\n";
    echo "   Note: This is expected if the index hasn't been regenerated yet\n";
}

echo "\n3. Instructions to Test\n";
echo "   To fully test class reference indexing:\n";
echo "   1. Run: php vendor/bin/guardrail.php -a config.json\n";
echo "   2. This will regenerate method_usage.json with class references\n";
echo "   3. Restart the LSP server in VSCode\n";
echo "   4. Try Go to Definition on any class name in your code\n";
echo "   5. It should now work on:\n";
echo "      - Type hints (function parameters, return types)\n";
echo "      - new ClassName() expressions\n";
echo "      - ClassName::staticMethod() calls\n";
echo "      - instanceof ClassName checks\n";
echo "      - catch (ExceptionClass \$e) blocks\n";
echo "      - extends/implements clauses\n";
echo "      - And any other place a class name appears!\n";

echo "\n=== Test Complete ===\n";
