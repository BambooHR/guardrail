<?php

require_once 'vendor/autoload.php';

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use BambooHR\Guardrail\Lsp\UsageIndex;
use BambooHR\Guardrail\Lsp\IncrementalIndexer;

echo "=== Testing Incremental Indexing ===\n\n";

// Load symbol table first
$symbolTable = new JsonSymbolTable('symbol_table.json', __DIR__);
$symbolTable->connect(0);

// Create a minimal config for testing
$config = new class($symbolTable, __DIR__) extends Config {
    public function __construct($symbolTable, string $basePath) {
        $this->config = ['index' => ['src']];
        $this->basePath = $basePath;
        $this->processes = 1;
        $this->outputLevel = 0;
        $this->symbolTable = $symbolTable;
    }
};

// Load usage index
$usageIndex = new UsageIndex(__DIR__ . '/method_usage.json', $symbolTable);

// Create incremental indexer
$indexer = new IncrementalIndexer($symbolTable, $config, $usageIndex);

echo "1. Testing Single File Indexing\n";
$testFile = __DIR__ . '/src/Checks/BaseCheck.php';

// Get current symbol count
$classFile = $symbolTable->getClassFile('BambooHR\\Guardrail\\Checks\\BaseCheck');
echo "   - BaseCheck class currently indexed: " . ($classFile ? "YES" : "NO") . "\n";

// Re-index the file
echo "   - Re-indexing {$testFile}...\n";
$success = $indexer->indexFile($testFile);
echo "   - Indexing " . ($success ? "SUCCEEDED" : "FAILED") . "\n";

// Verify it's still there
$classFile = $symbolTable->getClassFile('BambooHR\\Guardrail\\Checks\\BaseCheck');
echo "   - BaseCheck class after re-index: " . ($classFile ? "YES" : "NO") . "\n";

echo "\n2. Testing Single File Analysis\n";
echo "   - Analyzing {$testFile}...\n";
$usages = $indexer->analyzeFile($testFile);
echo "   - Collected " . count($usages) . " usage records\n";

if (!empty($usages)) {
    $sampleUsage = $usages[0];
    echo "   - Sample usage: function=" . ($sampleUsage['function'] ?? 'unknown') . 
         ", file=" . ($sampleUsage['file'] ?? 'unknown') . "\n";
}

echo "\n3. Testing Full File Update (Index + Analyze)\n";
echo "   - Updating {$testFile}...\n";
$success = $indexer->updateFile($testFile);
echo "   - Update " . ($success ? "SUCCEEDED" : "FAILED") . "\n";

echo "\n4. Testing Usage Index Incremental Update\n";
// Get usage count before
$beforeRefs = $usageIndex->getReferencesToClass('BambooHR\\Guardrail\\Checks\\ErrorConstants');
echo "   - References to ErrorConstants before: " . count($beforeRefs) . "\n";

// Remove and re-add
$relativePath = 'src/Checks/BaseCheck.php';
$usageIndex->removeFileUsages($relativePath);
echo "   - Removed usages from {$relativePath}\n";

// Re-analyze and add back
$usages = $indexer->analyzeFile($testFile);
if (!empty($usages)) {
    $usageIndex->addFileUsages($relativePath, $usages);
    echo "   - Added " . count($usages) . " usages back\n";
}

$afterRefs = $usageIndex->getReferencesToClass('BambooHR\\Guardrail\\Checks\\ErrorConstants');
echo "   - References to ErrorConstants after: " . count($afterRefs) . "\n";

echo "\n5. Testing Usage Index Save/Reload\n";
echo "   - Saving usage index...\n";
$usageIndex->save();
echo "   - Reloading usage index...\n";
$usageIndex->reload();
$reloadedRefs = $usageIndex->getReferencesToClass('BambooHR\\Guardrail\\Checks\\ErrorConstants');
echo "   - References to ErrorConstants after reload: " . count($reloadedRefs) . "\n";

echo "\n=== Test Complete ===\n\n";

echo "Summary:\n";
echo "- Incremental indexing: " . ($success ? "✓ Working" : "✗ Failed") . "\n";
echo "- Usage collection: " . (!empty($usages) ? "✓ Working" : "✗ Failed") . "\n";
echo "- Usage index updates: " . (count($afterRefs) > 0 ? "✓ Working" : "✗ Failed") . "\n";
echo "- Persistence: " . (count($reloadedRefs) === count($afterRefs) ? "✓ Working" : "✗ Failed") . "\n";

echo "\nNext Steps:\n";
echo "1. Start the LSP server with: php src/bin/guardrail-lsp.php symbol_table.json config.json method_usage.json\n";
echo "2. Configure your IDE to use the LSP server\n";
echo "3. Edit and save a PHP file\n";
echo "4. The indexes will update automatically!\n";
