<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table
$symbolTable = new JsonSymbolTable(__DIR__, 'symbol_table.json');

// Use reflection to access the index
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

echo "Searching for SymbolTable in index...\n\n";

// Search for SymbolTable in classes
if (isset($index['class'])) {
    echo "Classes in index: " . count($index['class']) . "\n";
    foreach ($index['class'] as $className => $data) {
        if (stripos($className, 'symboltable') !== false) {
            echo "Found: $className\n";
            echo "  File: {$data['file']}\n";
            
            // Try to get abstracted class
            $abstractClass = $symbolTable->getAbstractedClass($className);
            if ($abstractClass) {
                echo "  Can get abstracted class: YES\n";
                $node = $abstractClass->getNode();
                if ($node) {
                    $methods = $node->getMethods();
                    echo "  Methods: " . count($methods) . "\n";
                    foreach ($methods as $method) {
                        if (stripos($method->name->toString(), 'getclass') !== false) {
                            echo "    - {$method->name->toString()} at line {$method->getStartLine()}\n";
                        }
                    }
                } else {
                    echo "  Node: NULL\n";
                }
            } else {
                echo "  Can get abstracted class: NO\n";
            }
            echo "\n";
        }
    }
}

// Also check interfaces and traits
foreach (['interface', 'trait'] as $type) {
    if (isset($index[$type])) {
        echo "\n{$type}s in index: " . count($index[$type]) . "\n";
        foreach ($index[$type] as $name => $data) {
            if (stripos($name, 'symboltable') !== false) {
                echo "Found in $type: $name\n";
            }
        }
    }
}
