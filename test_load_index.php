<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;

// Load symbol table the same way LSP does
$symbolTablePath = __DIR__ . '/symbol_table.json';
$symbolTable = new JsonSymbolTable(dirname($symbolTablePath), basename($symbolTablePath));

// Access the index using reflection
$reflection = new \ReflectionClass($symbolTable);
$property = $reflection->getProperty('index');
$property->setAccessible(true);
$index = $property->getValue($symbolTable);

echo "Index structure:\n";
foreach ($index as $key => $value) {
    if (is_array($value)) {
        echo "  $key: " . count($value) . " items\n";
        if ($key === 'class' && count($value) > 0) {
            echo "    First 5 class names:\n";
            $count = 0;
            foreach ($value as $className => $data) {
                echo "      - $className\n";
                if (++$count >= 5) break;
            }
        }
    } else {
        echo "  $key: " . gettype($value) . "\n";
    }
}

// Check if the file is actually being read
echo "\nChecking file contents:\n";
$fileContents = file_get_contents($symbolTablePath);
$data = json_decode($fileContents, true);
if ($data) {
    echo "JSON decoded successfully\n";
    echo "Keys in JSON: " . implode(', ', array_keys($data)) . "\n";
    if (isset($data['class'])) {
        echo "Classes in JSON file: " . count($data['class']) . "\n";
        echo "First 5 classes from file:\n";
        $count = 0;
        foreach ($data['class'] as $className => $classData) {
            echo "  - $className\n";
            if (++$count >= 5) break;
        }
    }
} else {
    echo "Failed to decode JSON: " . json_last_error_msg() . "\n";
}
