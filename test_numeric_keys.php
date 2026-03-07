<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table
$symbolTablePath = __DIR__ . '/symbol_table.json';
$symbolTable = new JsonSymbolTable(dirname($symbolTablePath), basename($symbolTablePath));

// Access the index
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

echo "Index keys: " . implode(', ', array_keys($index)) . "\n\n";

// TYPE_CLASS = 1
if (isset($index[1])) {
    echo "Classes (key 1): " . count($index[1]) . " items\n";
    echo "Searching for SymbolTable:\n";
    foreach ($index[1] as $className => $data) {
        if (stripos($className, 'symboltable') !== false) {
            echo "  Found: '$className'\n";
            echo "    File: {$data['file']}\n";
            
            // Try to get abstracted class
            $abstractClass = $symbolTable->getAbstractedClass($className);
            if ($abstractClass) {
                echo "    ✓ getAbstractedClass works!\n";
                $node = $abstractClass->getNode();
                if ($node && method_exists($node, 'getMethods')) {
                    $methods = $node->getMethods();
                    echo "    Methods: " . count($methods) . "\n";
                    foreach ($methods as $method) {
                        $methodName = $method->name->toString();
                        if (stripos($methodName, 'getclass') !== false) {
                            echo "      ✓ FOUND METHOD: $methodName at line {$method->getStartLine()}\n";
                        }
                    }
                }
            } else {
                echo "    ✗ getAbstractedClass returned null\n";
            }
            echo "\n";
        }
    }
}

// Now test findMethodsByName
echo "\n=== Testing findMethodsByName ===\n";
use BambooHR\Guardrail\Lsp\SymbolResolver;

$resolver = new SymbolResolver($symbolTable);
$methods = $resolver->findMethodsByName('getClass');
echo "Found " . count($methods) . " methods named 'getClass'\n";
foreach ($methods as $method) {
    echo "  - {$method['class']}::{$method['method']} at {$method['file']}:{$method['line']}\n";
}
