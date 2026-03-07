<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table
$symbolTable = new JsonSymbolTable(__DIR__, 'symbol_table.json');
$symbolTable->connect(0); // Connect with process number 0

echo "=== Testing getAbstractedClass ===\n";
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
        echo "  ✓ SUCCESS! Got abstracted class\n";
        $node = $result->getNode();
        if ($node && method_exists($node, 'getMethods')) {
            $methods = $node->getMethods();
            echo "  Methods count: " . count($methods) . "\n";
            echo "  Looking for getClass method:\n";
            foreach ($methods as $method) {
                $methodName = $method->name->toString();
                if (stripos($methodName, 'getclass') !== false) {
                    echo "    ✓ FOUND: $methodName at line {$method->getStartLine()}\n";
                }
            }
        }
        break; // Found it, stop trying
    } else {
        echo "  ✗ FAILED - returned null\n";
    }
}

// Check what's in the index
echo "\n=== Checking index contents ===\n";
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

if (isset($index['class'])) {
    echo "Total classes in index: " . count($index['class']) . "\n";
    echo "\nClasses containing 'symboltable':\n";
    foreach ($index['class'] as $className => $data) {
        if (stripos($className, 'symboltable') !== false) {
            echo "  - $className\n";
        }
    }
}
