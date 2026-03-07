<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table
$symbolTablePath = __DIR__ . '/symbol_table.json';
$symbolTable = new JsonSymbolTable($symbolTablePath, dirname($symbolTablePath));
$symbolTable->connect(0);

// Check the index
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

echo "Index after connect():\n";
foreach ([1 => 'class', 2 => 'function', 3 => 'interface', 4 => 'trait'] as $key => $name) {
    if (isset($index[$key])) {
        echo "  $name (key $key): " . count($index[$key]) . " items\n";
        
        if ($name === 'class' && count($index[$key]) > 0) {
            echo "    First 10 classes:\n";
            $count = 0;
            foreach ($index[$key] as $className => $data) {
                echo "      - $className\n";
                if (++$count >= 10) break;
            }
            
            echo "\n    Searching for SymbolTable:\n";
            foreach ($index[$key] as $className => $data) {
                if (stripos($className, 'symboltable') !== false) {
                    echo "      FOUND: $className\n";
                    
                    // Try to get it
                    $abstractClass = $symbolTable->getAbstractedClass($className);
                    if ($abstractClass) {
                        echo "        ✓ Can get abstracted class\n";
                        $node = $abstractClass->getNode();
                        if ($node && method_exists($node, 'getMethods')) {
                            echo "        ✓ Has node with methods\n";
                            $methods = $node->getMethods();
                            echo "        Methods: " . count($methods) . "\n";
                            foreach ($methods as $method) {
                                if (stripos($method->name->toString(), 'getclass') !== false) {
                                    echo "          ✓✓✓ FOUND getClass at line {$method->getStartLine()}\n";
                                }
                            }
                        }
                    } else {
                        echo "        ✗ Cannot get abstracted class\n";
                    }
                }
            }
        }
    }
}
