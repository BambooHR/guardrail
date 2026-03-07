<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\SymbolResolver;

// Load symbol table with CORRECT constructor arguments
$symbolTablePath = __DIR__ . '/symbol_table.json';
$symbolTable = new JsonSymbolTable($symbolTablePath, dirname($symbolTablePath));
$symbolTable->connect(0);

echo "✓ Symbol table loaded successfully\n\n";

// Test findMethodsByName
echo "=== Testing findMethodsByName('getClass') ===\n";
$resolver = new SymbolResolver($symbolTable);
$methods = $resolver->findMethodsByName('getClass');
echo "Found " . count($methods) . " methods named 'getClass'\n\n";

if (count($methods) > 0) {
    echo "Methods found:\n";
    foreach ($methods as $method) {
        echo "  - {$method['class']}::{$method['method']} at {$method['file']}:{$method['line']}\n";
        if (stripos($method['class'], 'symboltable') !== false) {
            echo "    ✓✓✓ THIS IS THE ONE WE WANT! ✓✓✓\n";
        }
    }
} else {
    echo "✗ NO METHODS FOUND - Something is still wrong\n";
}
