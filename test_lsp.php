<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\UsageIndex;
use BambooHR\Guardrail\Lsp\SymbolResolver;

// Load symbol table
$symbolTable = new JsonSymbolTable(__DIR__, 'symbol_table.json');

// Load usage index
$usageIndex = new UsageIndex(__DIR__ . '/method_usage.json', $symbolTable);

// Create symbol resolver
$resolver = new SymbolResolver($symbolTable);

// Test: Look up usage at line 377 for getClass
$file = '/mnt/c/Users/jon/guardrail/src/SymbolTable/SymbolTable.php';
$line = 377;
$methodName = 'getClass';

echo "Testing usage lookup:\n";
echo "File: $file\n";
echo "Line: $line\n";
echo "Method: $methodName\n\n";

$usage = $usageIndex->getUsageAtPosition($file, $line, $methodName);
if ($usage) {
    echo "Found usage: " . json_encode($usage, JSON_PRETTY_PRINT) . "\n\n";
    
    // Resolve type IDs
    if (isset($usage['object_type_ids'])) {
        echo "Resolving types:\n";
        foreach ($usage['object_type_ids'] as $typeId) {
            $typeName = $usageIndex->resolveTypeId($typeId);
            echo "  Type ID $typeId => $typeName\n";
            
            // Try to find the method
            echo "  Looking for method {$usage['method']} in class $typeName\n";
            
            // Try with leading backslash
            $className = '\\' . ltrim($typeName, '\\');
            $method = $resolver->findMethod($className, $usage['method']);
            if ($method) {
                echo "  Found: {$method['file']}:{$method['line']}\n";
            } else {
                echo "  Not found via findMethod\n";
                
                // Try findMethodsByName
                echo "  Trying findMethodsByName...\n";
                $methods = $resolver->findMethodsByName($usage['method']);
                echo "  Found " . count($methods) . " methods named {$usage['method']}\n";
                
                foreach ($methods as $m) {
                    echo "    - {$m['class']}::{$m['method']} at {$m['file']}:{$m['line']}\n";
                }
            }
        }
    }
} else {
    echo "No usage found\n";
}
