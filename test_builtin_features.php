<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\UsageIndex;
use BambooHR\Guardrail\Lsp\SymbolResolver;
use BambooHR\Guardrail\Lsp\BuiltinResolver;
use BambooHR\Guardrail\Lsp\Handlers\CompletionHandler;
use BambooHR\Guardrail\Lsp\Handlers\ReferencesHandler;

echo "=== Testing Built-in PHP Function and Method Support ===\n\n";

// Load symbol table
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

// Load usage index
$usageIndex = new UsageIndex(__DIR__ . '/method_usage.json', $symbolTable);

// Create symbol resolver
$resolver = new SymbolResolver($symbolTable);

echo "1. Testing BuiltinResolver - Built-in Function Detection\n";
echo "   - Is 'exit' a built-in function? " . (BuiltinResolver::isBuiltinFunction('exit') ? 'YES' : 'NO') . "\n";
echo "   - Is 'array_map' a built-in function? " . (BuiltinResolver::isBuiltinFunction('array_map') ? 'YES' : 'NO') . "\n";
echo "   - Is 'myCustomFunction' a built-in function? " . (BuiltinResolver::isBuiltinFunction('myCustomFunction') ? 'YES' : 'NO') . "\n";

$exitInfo = BuiltinResolver::getBuiltinFunction('exit');
if ($exitInfo) {
    echo "   - exit() signature: {$exitInfo['signature']}\n";
}

echo "\n2. Testing BuiltinResolver - Finding Function Calls\n";
$testFile = __DIR__ . '/src/SymbolTable/SymbolTable.php';
$exitCalls = BuiltinResolver::findFunctionCalls($testFile, 'exit');
echo "   - Found " . count($exitCalls) . " calls to exit() in SymbolTable.php\n";
if (!empty($exitCalls)) {
    echo "   - First call at line {$exitCalls[0]['line']}, column {$exitCalls[0]['column']}\n";
}

echo "\n3. Testing ReferencesHandler - Built-in Function References\n";
$referencesHandler = new ReferencesHandler($usageIndex, __DIR__);

// Simulate finding references to exit()
$params = (object)[
    'textDocument' => (object)[
        'uri' => 'file:///' . str_replace('\\', '/', $testFile)
    ],
    'position' => (object)[
        'line' => 0,
        'character' => 0
    ]
];

// We'll test by directly calling the built-in function search
echo "   - Searching for exit() calls in project...\n";
$phpFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/src', RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
        if (count($phpFiles) >= 100) break; // Limit for testing
    }
}
echo "   - Scanning " . count($phpFiles) . " files\n";

$references = BuiltinResolver::findBuiltinFunctionReferences($phpFiles, 'exit');
echo "   - Found " . count($references) . " references to exit()\n";
if (!empty($references)) {
    echo "   - Sample references:\n";
    for ($i = 0; $i < min(5, count($references)); $i++) {
        $ref = $references[$i];
        $shortPath = basename(dirname($ref['file'])) . '/' . basename($ref['file']);
        echo "     * {$shortPath}:{$ref['line']}\n";
    }
}

echo "\n4. Testing CompletionHandler - Built-in Methods\n";
$completionHandler = new CompletionHandler($resolver, $usageIndex);

// Test with a class that extends a built-in class (if we have one)
// For now, test with Exception which is a built-in class
echo "   - Getting methods for Exception class...\n";
$exceptionMethods = BuiltinResolver::getBuiltinMethods('Exception');
echo "   - Found " . count($exceptionMethods) . " methods in Exception class\n";
echo "   - Sample methods: " . implode(', ', array_slice($exceptionMethods, 0, 10)) . "\n";

$getMessageInfo = BuiltinResolver::getBuiltinMethod('Exception', 'getMessage');
if ($getMessageInfo) {
    echo "   - getMessage() signature: {$getMessageInfo['signature']}\n";
}

echo "\n5. Testing Completion with Built-in Parent Methods\n";
// Find a class in the project that extends a built-in class
echo "   - Testing completion for a class with built-in parent...\n";
// We'll use SymbolTable which might have ObjectCache or other dependencies

// Create a test scenario
$testCompletionFile = __DIR__ . '/src/SymbolTable/SymbolTable.php';
$testLine = 70; // Line with $this->
$testChar = 17;

$params = (object)[
    'textDocument' => (object)[
        'uri' => 'file:///' . str_replace('\\', '/', $testCompletionFile)
    ],
    'position' => (object)[
        'line' => $testLine,
        'character' => $testChar
    ]
];

echo "   - Requesting completion at SymbolTable.php:{$testLine}:{$testChar}\n";
$completions = $completionHandler->handle($params);

if ($completions) {
    echo "   - Found " . count($completions) . " completion items\n";
    
    // Check if any built-in methods are included
    $builtinCount = 0;
    foreach ($completions as $item) {
        if (strpos($item->signature ?? '', '::') !== false) {
            $parts = explode('::', $item->signature);
            if (count($parts) > 1 && class_exists($parts[0])) {
                $builtinCount++;
            }
        }
    }
    echo "   - Includes built-in/inherited methods: " . ($builtinCount > 0 ? 'YES' : 'NO') . "\n";
} else {
    echo "   - No completions found\n";
}

echo "\n=== Tests Complete ===\n";
