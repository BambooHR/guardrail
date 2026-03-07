<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\SymbolResolver;

// Load symbol table the CORRECT way (with connect)
$symbolTablePath = __DIR__ . '/symbol_table.json';
$symbolTable = new JsonSymbolTable(dirname($symbolTablePath), basename($symbolTablePath));
$symbolTable->connect(0); // THIS IS THE KEY!

echo "=== Symbol table loaded ===\n";

// Check the index
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

if (isset($index[1])) {
    echo "Classes in index: " . count($index[1]) . "\n\n";
    
    echo "Searching for SymbolTable:\n";
    foreach ($index[1] as $className => $data) {
        if (stripos($className, 'symboltable') !== false && stripos($className, 'jsonsymboltable') === false) {
            echo "  Found: '$className'\n";
            
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
                            echo "      ✓ FOUND: $methodName at line {$method->getStartLine()}\n";
                        }
                    }
                }
            }
            echo "\n";
        }
    }
}

echo "\n=== Testing findMethodsByName ===\n";
$resolver = new SymbolResolver($symbolTable);
$methods = $resolver->findMethodsByName('getClass');
echo "Found " . count($methods) . " methods named 'getClass'\n";
foreach ($methods as $method) {
    echo "  - {$method['class']}::{$method['method']} at {$method['file']}:{$method['line']}\n";
}
