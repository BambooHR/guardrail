<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestPsr4Check extends TestSuiteSetup {

	/**
	 * @dataProvider fileProvider
	 * @param string $file
	 * @param int    $expectedErrorCount
	 * @return void
	 * @rapid-unit   Checks:Psr4:Detects
	 */
	public function testCheckBehavior(string $file, int $expectedErrorCount): void {
		$roots = [
			'Alpha' => 'TestData/TestPsr4Check/Alpha',
			'AlphaSDK' => 'TestData/TestPsr4Check/Alpha/SDK',
			'Beta' => 'TestData/TestPsr4Check/Beta',
			'App\\Delta' => 'TestData/TestPsr4Check/Delta',
		];
		$basePath = __DIR__; // Override the basePath so that namespaces resolve correctly
		$this->assertEquals(
			$expectedErrorCount,
			$this->runAnalyzerOnFile(
				$file,
				ErrorConstants::TYPE_PSR4,
				['basePath' => $basePath, 'psr-roots' => $roots]
			),
			'Failed using Guardrail psr-roots'
		);

		$composerRoots = [];
		foreach ($roots as $root => $dir) {
			$composerRoots[$root. '\\'] = $dir;
		}
		$this->assertEquals(
			$expectedErrorCount,
			$this->runAnalyzerOnFile(
				$file,
				ErrorConstants::TYPE_PSR4,
				['basePath' => $basePath, 'psr-roots' => $composerRoots]
			),
			'Failed using composer roots'
		);
	}

	/**
	 * @return string[][]
	 */
	public static function fileProvider(): array {
		return [
			['/Alpha/SDK/One.php', 0],
			['/Alpha/Two.php', 0],
			['/Alpha/SDK/Three.php', 0],
			['/Beta/Gamma/Four.php', 0],
			['/Beta/Five.php', 1],
			['/Delta/Six.php', 0],
			['/Delta/Seven.php', 1],
			['/Alpha/Eight.php', 0],
		];
	}
}
