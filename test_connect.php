<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table
$symbolTable = new JsonSymbolTable(__DIR__, 'symbol_table.json');

echo "Before connect:\n";
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);
echo "Index is: " . (is_array($index) ? "array with " . count($index) . " keys" : gettype($index)) . "\n\n";

// Call connect to load the index
echo "Calling connect()...\n";
$symbolTable->connect();

echo "\nAfter connect:\n";
$index = $property->getValue($symbolTable);
echo "Index is: " . (is_array($index) ? "array with " . count($index) . " keys" : gettype($index)) . "\n";

if (is_array($index) && isset($index['class'])) {
    echo "Total classes: " . count($index['class']) . "\n";
    echo "\nSearching for SymbolTable:\n";
    foreach ($index['class'] as $className => $data) {
        if (stripos($className, 'symboltable') !== false) {
            echo "  Found: $className in {$data['file']}\n";
        }
    }
}

// Now test getAbstractedClass
echo "\n=== Testing getAbstractedClass after connect ===\n";
$variations = [
    'BambooHR\Guardrail\SymbolTable\SymbolTable',
    '\BambooHR\Guardrail\SymbolTable\SymbolTable',
    'bamboohr\guardrail\symboltable\symboltable',
];

foreach ($variations as $className) {
    echo "Trying: '$className'\n";
    $result = $symbolTable->getAbstractedClass($className);
    if ($result) {
        echo "  SUCCESS!\n";
        $node = $result->getNode();
        if ($node && method_exists($node, 'getMethods')) {
            $methods = $node->getMethods();
            echo "  Methods: " . count($methods) . "\n";
            foreach ($methods as $method) {
                if (stripos($method->name->toString(), 'getclass') !== false) {
                    echo "    - {$method->name->toString()} at line {$method->getStartLine()}\n";
                }
            }
        }
        break;
    } else {
        echo "  FAILED\n";
    }
}
