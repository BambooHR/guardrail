<?php

declare(strict_types=1);

namespace BambooHR\Guardrail\Tests\units\Output;

use BambooHR\Guardrail\Output\XUnitOutput;
use BambooHR\Guardrail\Tests\TestConfig;
use PHPUnit\Framework\TestCase;

class XUnitOutputTest extends TestCase {

	/**
	 * @dataProvider shouldEmitIgnoreProvider
	 */
	public function testShouldEmitIgnoreArray($fileName, $expected) : void {
		$emitList = [
			[
				'ignore' => ['**/app/BambooHR/Controller/TimeOff/**/*', '**/app/BambooHR/Silo/Benefits/Shared/**/*'],
			]
		];
		$config = new TestConfig('', $emitList);
		$output = new XUnitOutput($config);
		$actual = $output->shouldEmit($fileName, '', 0);
		self::assertEquals($expected, $actual);
	}

	public static function shouldEmitIgnoreProvider() {
		return [
			['app/BambooHR/Silo/Benefits/Shared/BenefitCalculator/Settings/GroupPlanCalculatorSetting/RateSplittableGroupPlanCalculatorSetting.php', false],
			['app/BambooHR/Controller/SomeTestOne.php', true]
		];
	}

	/**
	 * @dataProvider shouldEmitGlobProvider
	 */
	public function testShouldEmitGlobArray($fileName, $expected) : void {
		$emitList = [
			[
				'glob' => ['**/app/BambooHR/Silo/Benefits/Shared/**/*'],
			]
		];
		$config = new TestConfig('', $emitList);
		$output = new XUnitOutput($config);
		$actual = $output->shouldEmit($fileName, '', 0);
		self::assertEquals($expected, $actual);
	}

	public static function shouldEmitGlobProvider() {
		return [
			['app/BambooHR/Silo/Benefits/Shared/BenefitCalculator/Settings/GroupPlanCalculatorSetting/RateSplittableGroupPlanCalculatorSetting.php', true],
			['app/BambooHR/Controller/SomeTestOne.php', false]
		];
	}

}
