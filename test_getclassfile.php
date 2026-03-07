<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

$className = 'bamboohr\guardrail\symboltable\symboltable';

echo "Testing getClassFile with: '$className'\n";
$file = $symbolTable->getClassFile($className);
if ($file) {
    echo "✓ getClassFile returned: $file\n";
} else {
    echo "✗ getClassFile returned null\n";
    
    // Check if it's in the index
    $reflection = new \ReflectionClass($symbolTable);
    $property = $reflection->getProperty('index');
    $property->setAccessible(true);
    $index = $property->getValue($symbolTable);
    
    if (isset($index[1][$className])) {
        echo "  But the class IS in the index!\n";
        echo "  File in index: {$index[1][$className]['file']}\n";
    }
}

// Also test getAbstractedClass
echo "\nTesting getAbstractedClass:\n";
$abstractClass = $symbolTable->getAbstractedClass($className);
if ($abstractClass) {
    echo "✓ getAbstractedClass worked\n";
} else {
    echo "✗ getAbstractedClass returned null\n";
}
