<?php

declare(strict_types=1);

namespace BambooHR\Guardrail\Tests\units\Output;

use BambooHR\Guardrail\Output\XUnitOutput;
use BambooHR\Guardrail\Tests\TestConfig;
use PHPUnit\Framework\TestCase;

class XUnitOutputTest extends TestCase {

	/**
	 * @dataProvider shouldEmitProvider
	 */
	public function testShouldEmitIgnoreArray($fileName, $expected) : void {
		$emitList = [
			[
				'ignore' => ['**/app/BambooHR/Silo/Benefits/Shared/**/*'],
			]
		];
		$config = new TestConfig('', $emitList);
		$output = new XUnitOutput($config);
		$actual = $output->shouldEmit($fileName, '', 0);
		self::assertEquals($expected, $actual);
	}

	public function shouldEmitProvider() {
		return [
			['app/BambooHR/Silo/Benefits/Shared/BenefitCalculator/Settings/GroupPlanCalculatorSetting/RateSplittableGroupPlanCalculatorSetting.php', false],
			['app/BambooHR/Controller/SomeTestOne.php', true]
		];
	}

}
