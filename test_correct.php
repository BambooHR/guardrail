<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table the CORRECT way (like the LSP server does)
$symbolTablePath = __DIR__ . '/symbol_table.json';
$symbolTable = new JsonSymbolTable(dirname($symbolTablePath), basename($symbolTablePath));

echo "Symbol table created\n";
echo "Directory: " . dirname($symbolTablePath) . "\n";
echo "Filename: " . basename($symbolTablePath) . "\n\n";

// Check the index
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

if (isset($index['class'])) {
    echo "Total classes in index: " . count($index['class']) . "\n";
    echo "\nClasses containing 'symboltable' (case-insensitive):\n";
    foreach ($index['class'] as $className => $data) {
        if (stripos($className, 'symboltable') !== false) {
            echo "  - '$className' in {$data['file']}\n";
        }
    }
}

echo "\n=== Testing getAbstractedClass ===\n";
$variations = [
    'bamboohr\guardrail\symboltable\symboltable',
    '\bamboohr\guardrail\symboltable\symboltable',
    'BambooHR\Guardrail\SymbolTable\SymbolTable',
    '\BambooHR\Guardrail\SymbolTable\SymbolTable',
];

foreach ($variations as $className) {
    echo "\nTrying: '$className'\n";
    $result = $symbolTable->getAbstractedClass($className);
    if ($result) {
        echo "  ✓ SUCCESS!\n";
        $node = $result->getNode();
        if ($node && method_exists($node, 'getMethods')) {
            $methods = $node->getMethods();
            echo "  Methods: " . count($methods) . "\n";
            foreach ($methods as $method) {
                $methodName = $method->name->toString();
                if (stripos($methodName, 'getclass') !== false) {
                    echo "    ✓ FOUND: $methodName at line {$method->getStartLine()}\n";
                }
            }
        }
        break;
    } else {
        echo "  ✗ FAILED\n";
    }
}
