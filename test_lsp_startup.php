<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\Server;

echo "Testing LSP Server Startup...\n\n";

try {
    // Load symbol table
    echo "1. Loading symbol table...\n";
    $symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
    $symbolTable->connect(0);
    echo "   ✓ Symbol table loaded\n\n";

    // Create minimal config
    echo "2. Creating config...\n";
    $config = new class($symbolTable, __DIR__) extends Config {
        public function __construct($symbolTable, string $basePath) {
            $this->config = ['index' => ['src']];
            $this->basePath = $basePath;
            $this->processes = 1;
            $this->outputLevel = 0;
            $this->symbolTable = $symbolTable;
        }
    };
    echo "   ✓ Config created\n\n";

    // Create LSP server
    echo "3. Creating LSP server...\n";
    $usageFilePath = __DIR__ . '/method_usage.json';
    $server = new Server($symbolTable, $usageFilePath, $config);
    echo "   ✓ Server created\n\n";

    // Test initialize
    echo "4. Testing initialize...\n";
    $initParams = (object)[
        'rootUri' => 'file://' . __DIR__,
        'capabilities' => (object)[]
    ];
    $result = $server->handleInitialize($initParams);
    echo "   ✓ Initialize successful\n";
    echo "   - textDocumentSync: " . json_encode($result->capabilities->textDocumentSync) . "\n";
    echo "   - definitionProvider: " . ($result->capabilities->definitionProvider ? 'true' : 'false') . "\n\n";

    // Test diagnostics callback
    echo "5. Testing diagnostics callback...\n";
    $diagnosticsReceived = false;
    $server->setDiagnosticsCallback(function($uri, $diagnostics) use (&$diagnosticsReceived) {
        $diagnosticsReceived = true;
        echo "   ✓ Diagnostics callback invoked\n";
        echo "   - URI: $uri\n";
        echo "   - Diagnostic count: " . count($diagnostics) . "\n";
    });
    echo "   ✓ Callback registered\n\n";

    echo "=== All Tests Passed ===\n\n";
    echo "The LSP server should start successfully.\n";
    echo "If you're still seeing crashes, check:\n";
    echo "1. VSCode LSP client configuration\n";
    echo "2. PHP error logs for runtime errors\n";
    echo "3. Ensure all paths are correct in your IDE settings\n";

} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
