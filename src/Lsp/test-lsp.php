<?php

/**
 * Simple test script to verify LSP server components
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use BambooHR\Guardrail\Lsp\PositionMapper;
use BambooHR\Guardrail\Lsp\FuzzyMatcher;

echo "Testing Guardrail LSP Components\n";
echo "=================================\n\n";

// Test 1: PositionMapper
echo "Test 1: PositionMapper\n";
echo "----------------------\n";

$guardrailPos = "10,5";
$lspPos = PositionMapper::guardrailToLsp($guardrailPos);
echo "Guardrail position: $guardrailPos\n";
echo "LSP position: line={$lspPos->line}, character={$lspPos->character}\n";
echo "Expected: line=9, character=5\n";
echo $lspPos->line === 9 && $lspPos->character === 5 ? "✓ PASS\n" : "✗ FAIL\n";
echo "\n";

// Test 2: URI conversion
echo "Test 2: URI Conversion\n";
echo "----------------------\n";

$windowsPath = "c:/Users/jon/test.php";
$uri = PositionMapper::pathToUri($windowsPath);
echo "Path: $windowsPath\n";
echo "URI: $uri\n";
echo "Expected: file:///c:/Users/jon/test.php\n";
echo $uri === "file:///c:/Users/jon/test.php" ? "✓ PASS\n" : "✗ FAIL\n";

$convertedBack = PositionMapper::uriToPath($uri);
echo "Converted back: $convertedBack\n";
echo $convertedBack === $windowsPath ? "✓ PASS\n" : "✗ FAIL\n";
echo "\n";

// Test 3: Fuzzy Matcher
echo "Test 3: Fuzzy Matcher\n";
echo "---------------------\n";

$symbols = [
	'UserController',
	'UserService',
	'AdminController',
	'user_helper',
	'upload_config',
	'UpdateChecker',
	'DatabaseConnection'
];

$testCases = [
	['query' => 'UC', 'expected' => ['UserController', 'UpdateChecker']],
	['query' => 'user', 'expected' => ['UserController', 'UserService', 'user_helper']],
	['query' => 'ctrl', 'expected' => ['UserController', 'AdminController']],
	['query' => 'uc', 'expected' => ['UserController', 'upload_config', 'UpdateChecker']],
];

foreach ($testCases as $test) {
	$query = $test['query'];
	$expected = $test['expected'];
	
	$results = FuzzyMatcher::filterAndSort($query, $symbols, 10);
	$resultNames = array_map(fn($r) => $r['name'], $results);
	
	echo "Query: '$query'\n";
	echo "Results: " . implode(', ', $resultNames) . "\n";
	echo "Expected to include: " . implode(', ', $expected) . "\n";
	
	$allFound = true;
	foreach ($expected as $exp) {
		if (!in_array($exp, $resultNames)) {
			$allFound = false;
			break;
		}
	}
	
	echo $allFound ? "✓ PASS\n" : "✗ FAIL\n";
	echo "\n";
}

// Test 4: CamelCase matching
echo "Test 4: CamelCase Matching\n";
echo "--------------------------\n";

$score1 = FuzzyMatcher::score('UC', 'UserController');
$score2 = FuzzyMatcher::score('UC', 'UpdateChecker');
$score3 = FuzzyMatcher::score('UC', 'user_controller');

echo "Score for 'UC' vs 'UserController': $score1\n";
echo "Score for 'UC' vs 'UpdateChecker': $score2\n";
echo "Score for 'UC' vs 'user_controller': $score3\n";
echo "All scores > 0: " . ($score1 > 0 && $score2 > 0 && $score3 > 0 ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

echo "=================================\n";
echo "All component tests completed!\n";
