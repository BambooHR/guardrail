<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test that @throws documentation correctly matches thrown exceptions
 */
class TestThrowsDocumentation extends TestSuiteSetup {

	public function testThrowsWithUseStatement() {
		$code = <<<'ENDCODE'
<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Exceptions\SocketException;

class Socket {
	/**
	 * @throws SocketException
	 */
	public static function writeComplete($fp, $string) {
		throw new SocketException("test");
	}
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_UNDOCUMENTED_EXCEPTION, ["basePath"=>"/"]);
		
		// Should have 0 errors - the exception is documented
		$this->assertEquals(0, $output->getErrorCount(), 
			"@throws SocketException should match 'throw new SocketException'"
		);
	}

	public function testThrowsWithFullyQualifiedName() {
		$code = <<<'ENDCODE'
<?php

namespace BambooHR\Guardrail;

class Socket {
	/**
	 * @throws \BambooHR\Guardrail\Exceptions\SocketException
	 */
	public static function writeComplete($fp, $string) {
		throw new \BambooHR\Guardrail\Exceptions\SocketException("test");
	}
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_UNDOCUMENTED_EXCEPTION, ["basePath"=>"/"]);
		
		// Should have 0 errors - the exception is documented with FQN
		$this->assertEquals(0, $output->getErrorCount(), 
			"@throws with FQN should match 'throw new' with FQN"
		);
	}

	public function testThrowsRelativeNameWithUseStatement() {
		$code = <<<'ENDCODE'
<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Exceptions\SocketException;

class Socket {
	/**
	 * @throws SocketException
	 */
	public static function writeComplete($fp, $string) {
		throw new \BambooHR\Guardrail\Exceptions\SocketException("test");
	}
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_UNDOCUMENTED_EXCEPTION, ["basePath"=>"/"]);
		
		// Should have 0 errors - relative name in @throws should be resolved to FQN
		$this->assertEquals(0, $output->getErrorCount(), 
			"@throws SocketException (with use) should match 'throw new \\BambooHR\\Guardrail\\Exceptions\\SocketException'"
		);
	}
}
