<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\UsageIndex;
use BambooHR\Guardrail\Lsp\SymbolResolver;
use BambooHR\Guardrail\Lsp\Handlers\CompletionHandler;

// Load symbol table
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0); // Connect to load the index

// Load usage index
$usageIndex = new UsageIndex(__DIR__ . '/method_usage.json', $symbolTable);

// Create symbol resolver
$resolver = new SymbolResolver($symbolTable);

// Create completion handler
$handler = new CompletionHandler($resolver, $usageIndex);

// Test: Simulate completion request at $this-> in SymbolTable.php
$testFile = __DIR__ . '/src/SymbolTable/SymbolTable.php';
$testLine = 70; // Line 71 in editor (0-indexed for LSP)
$testChar = 17; // Character position after "$this->get"

// Create mock params object
$params = (object)[
    'textDocument' => (object)[
        'uri' => 'file:///' . str_replace('\\', '/', $testFile)
    ],
    'position' => (object)[
        'line' => $testLine,
        'character' => $testChar
    ]
];

echo "Testing completion at {$testFile}:{$testLine}:{$testChar}\n\n";

// Call handler
$completions = $handler->handle($params);

if ($completions) {
    echo "Found " . count($completions) . " completion items:\n\n";
    foreach ($completions as $item) {
        echo "- {$item->label}";
        if (isset($item->detail)) {
            echo " : {$item->detail}";
        }
        echo "\n";
    }
} else {
    echo "No completions found\n";
}
