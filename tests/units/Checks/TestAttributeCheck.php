<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


class TestAttributeCheck extends TestSuiteSetup {
    public function testValidAttributeDoesNotEmitError() {
        $output = $this->analyzeFileToOutput('.1.inc', []);
        $this->assertEquals(0, $output->getErrorCount());
    }

    public function testUndefinedAttributeEmitsError() {
        $output = $this->analyzeFileToOutput('.2.inc', [ErrorConstants::TYPE_UNKNOWN_CLASS]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_UNKNOWN_CLASS] ?? 0);
    }

    public function testClassThatIsNotAnAttributeEmitsError() {
        $output = $this->analyzeFileToOutput('.3.inc', [ErrorConstants::TYPE_ATTRIBUTE_NOT_ATTRIBUTE]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_ATTRIBUTE_NOT_ATTRIBUTE] ?? 0);
    }

    public function testAttributeOnWrongTargetEmitsError() {
        $output = $this->analyzeFileToOutput('.4.inc', [ErrorConstants::TYPE_ATTRIBUTE_WRONG_TARGET]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_ATTRIBUTE_WRONG_TARGET] ?? 0);
    }

    public function testNonRepeatableAttributeEmitsError() {
        $output = $this->analyzeFileToOutput('.5.inc', [ErrorConstants::TYPE_ATTRIBUTE_NOT_REPEATABLE]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_ATTRIBUTE_NOT_REPEATABLE] ?? 0);
    }

    public function testAttributeWithPrivateConstructorEmitsError() {
        $output = $this->analyzeFileToOutput('.6.inc', [ErrorConstants::TYPE_SCOPE_ERROR]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_SCOPE_ERROR] ?? 0);
    }

    public function testWrongNumberOfConstructorParamsEmitsError() {
        $output = $this->analyzeFileToOutput('.7.inc', [ErrorConstants::TYPE_SIGNATURE_COUNT]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_SIGNATURE_COUNT] ?? 0);
    }

    public function testWrongConstructorParamTypeEmitsError() {
        $output = $this->analyzeFileToOutput('.8.inc', [ErrorConstants::TYPE_SIGNATURE_TYPE]);
        $counts = $output->getCounts();
        $this->assertEquals(1, $counts[ErrorConstants::TYPE_SIGNATURE_TYPE] ?? 0);
    }
}
