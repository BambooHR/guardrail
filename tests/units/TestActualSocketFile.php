<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test the actual Socket.php file to ensure @throws works correctly
 */
class TestActualSocketFile extends TestSuiteSetup {

	public function testSocketFileHasNoUndocumentedThrows() {
		$socketFile = file_get_contents(__DIR__ . '/../../src/Socket.php');
		
		$output = $this->analyzeStringToOutput("Socket.php", $socketFile, ErrorConstants::TYPE_UNDOCUMENTED_EXCEPTION, ["basePath"=>"/"]);
		
		// Should have 0 undocumented exception errors
		$this->assertEquals(0, $output->getErrorCount(), 
			"Socket.php should have no undocumented exception errors - @throws SocketException should match throw new SocketException"
		);
	}
}
