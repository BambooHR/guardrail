<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\Server;
use BambooHR\Guardrail\Lsp\PositionMapper;

echo "=== Testing Diagnostics Flow ===\n\n";

// Load symbol table
echo "1. Loading symbol table...\n";
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);
echo "   ✓ Loaded\n\n";

// Load config
echo "2. Loading config...\n";
$config = new Config(['', 'self.json']);
echo "   ✓ Loaded\n\n";

// Create server
echo "3. Creating LSP server...\n";
$server = new Server($symbolTable, __DIR__ . '/method_usage.json', $config);
echo "   ✓ Created\n\n";

// Set up diagnostics callback
echo "4. Setting up diagnostics callback...\n";
$capturedDiagnostics = null;
$server->setDiagnosticsCallback(function($uri, $diagnostics) use (&$capturedDiagnostics) {
    $capturedDiagnostics = [
        'uri' => $uri,
        'diagnostics' => $diagnostics
    ];
    echo "   ✓ Callback invoked!\n";
    echo "   - URI: $uri\n";
    echo "   - Diagnostic count: " . count($diagnostics) . "\n";
    foreach ($diagnostics as $i => $diag) {
        echo "   - Diagnostic " . ($i + 1) . ": " . $diag->message . "\n";
    }
});
echo "   ✓ Callback registered\n\n";

// Simulate didSave notification
echo "5. Simulating textDocument/didSave notification...\n";
$testFile = __DIR__ . '/test_diagnostic.php';
$testUri = PositionMapper::pathToUri($testFile);

$didSaveParams = (object)[
    'textDocument' => (object)[
        'uri' => $testUri
    ]
];

echo "   - File: $testFile\n";
echo "   - URI: $testUri\n";
echo "   - Calling handleDidSave()...\n\n";

$server->handleDidSave($didSaveParams);

echo "\n6. Results:\n";
if ($capturedDiagnostics) {
    echo "   ✓ Diagnostics were published!\n";
    echo "   - URI: " . $capturedDiagnostics['uri'] . "\n";
    echo "   - Count: " . count($capturedDiagnostics['diagnostics']) . "\n\n";
    
    if (count($capturedDiagnostics['diagnostics']) > 0) {
        echo "   Diagnostics:\n";
        foreach ($capturedDiagnostics['diagnostics'] as $i => $diag) {
            echo "   " . ($i + 1) . ". Line " . ($diag->range->start->line + 1) . ": " . $diag->message . "\n";
            echo "      Type: " . ($diag->code ?? 'N/A') . "\n";
            echo "      Severity: " . $diag->severity . " (1=Error, 2=Warning)\n";
        }
    } else {
        echo "   ⚠ No diagnostics generated (file may have no errors)\n";
    }
} else {
    echo "   ❌ Diagnostics callback was NOT invoked\n";
    echo "   This means either:\n";
    echo "   - handleDidSave() didn't call the callback\n";
    echo "   - IncrementalIndexer is not available\n";
    echo "   - DidSaveHandler returned null\n";
}

echo "\n=== Test Complete ===\n";
