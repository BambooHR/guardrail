<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

echo "Searching for SymbolTable class in index...\n\n";

foreach ($index[1] as $className => $data) {
    // Look for the base SymbolTable class (not Json, not TypeString)
    if (strpos($className, 'symboltable') !== false) {
        $parts = explode('\\', $className);
        $lastPart = end($parts);
        
        // We want the one that's just "symboltable", not "jsonsymboltable" or "typestringtable"
        if ($lastPart === 'symboltable') {
            echo "FOUND IT!\n";
            echo "Class name in index: '$className'\n";
            echo "File: {$data['file']}\n\n";
            
            // Try to get it
            $result = $symbolTable->getAbstractedClass($className);
            if ($result) {
                echo "✓ getAbstractedClass('$className') works!\n";
            } else {
                echo "✗ getAbstractedClass('$className') returned null\n";
            }
            
            break;
        }
    }
}
