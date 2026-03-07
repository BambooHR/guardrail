<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

// Find SymbolTable class
$className = 'bamboohr\guardrail\symboltable\symboltable';
if (isset($index[1][$className])) {
    $data = $index[1][$className];
    echo "SymbolTable class data:\n";
    echo "File: {$data['file']}\n";
    echo "Data: " . substr($data['data'], 0, 500) . "...\n\n";
    
    // The 'data' field contains serialized method information
    // Let's see if we can parse it to find getClass
    if (preg_match('/MgetClass/', $data['data'])) {
        echo "✓ Found 'MgetClass' in the serialized data\n";
        echo "This means the method is in the symbol table\n\n";
    }
    
    // Try to get the abstracted class and check the actual PHP file
    $abstractClass = $symbolTable->getAbstractedClass($className);
    if ($abstractClass) {
        echo "✓ Got abstracted class\n";
        $method = $abstractClass->getMethod('getClass');
        if ($method) {
            echo "✓ Got method\n";
            echo "Method starting line: " . $method->getStartingLine() . "\n";
            
            // The line is -1, which means we need to parse the actual file
            // Let's check the actual file
            $file = $data['file'];
            echo "\nChecking actual file: $file\n";
            if (file_exists($file)) {
                $lines = file($file);
                foreach ($lines as $lineNum => $lineContent) {
                    if (preg_match('/function\s+getClass\s*\(/', $lineContent)) {
                        echo "✓ Found 'function getClass' at line " . ($lineNum + 1) . "\n";
                        break;
                    }
                }
            }
        }
    }
}
