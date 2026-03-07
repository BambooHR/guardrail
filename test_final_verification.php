<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\SymbolResolver;

// Load symbol table
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

echo "=== Testing findMethod with mixed-case class name ===\n\n";

$resolver = new SymbolResolver($symbolTable);

// This is what comes from the usage data (mixed case)
$className = 'BambooHR\Guardrail\SymbolTable\SymbolTable';
$methodName = 'getClass';

echo "Looking for: {$className}::{$methodName}\n\n";

$result = $resolver->findMethod($className, $methodName);

if ($result) {
    echo "✓✓✓ SUCCESS! ✓✓✓\n";
    echo "Found: {$result['class']}::{$result['method']}\n";
    echo "File: {$result['file']}\n";
    echo "Line: {$result['line']}\n";
} else {
    echo "✗ FAILED - returned null\n";
}
