<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table
$symbolTable = new JsonSymbolTable(__DIR__, 'symbol_table.json');

echo "Testing different class name formats:\n\n";

$variations = [
    'BambooHR\Guardrail\SymbolTable\SymbolTable',
    '\BambooHR\Guardrail\SymbolTable\SymbolTable',
    'bamboohr\guardrail\symboltable\symboltable',
    '\bamboohr\guardrail\symboltable\symboltable',
];

foreach ($variations as $className) {
    echo "Trying: '$className'\n";
    $result = $symbolTable->getAbstractedClass($className);
    if ($result) {
        echo "  SUCCESS! Got abstracted class\n";
        $node = $result->getNode();
        if ($node) {
            echo "  Got node, methods: " . count($node->getMethods()) . "\n";
            foreach ($node->getMethods() as $method) {
                $name = $method->name->toString();
                if (stripos($name, 'getclass') !== false) {
                    echo "    Found method: $name at line {$method->getStartLine()}\n";
                }
            }
        }
    } else {
        echo "  FAILED - returned null\n";
    }
    
    // Also try getClassFile
    $file = $symbolTable->getClassFile($className);
    if ($file) {
        echo "  getClassFile returned: $file\n";
    } else {
        echo "  getClassFile returned: null\n";
    }
    echo "\n";
}

// Let's also check what classes ARE in the symbol table
echo "\n=== Checking what's actually in the symbol table ===\n";
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

if (isset($index['class'])) {
    echo "Total classes in index: " . count($index['class']) . "\n";
    echo "First 10 class names:\n";
    $count = 0;
    foreach ($index['class'] as $className => $data) {
        echo "  - $className\n";
        if (++$count >= 10) break;
    }
}
