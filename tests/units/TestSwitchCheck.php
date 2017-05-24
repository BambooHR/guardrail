<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\SwitchCheck;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Class TestSwitchCheck
 */
class TestSwitchCheck extends TestCase {

	/**
	 * parseText
	 *
	 * @param string $txt The text to parse
	 *
	 * @return null|\PhpParser\Node[]
	 */
	static function parseText($txt) {
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		return $parser->parse($txt);
	}

	/**
	 * testAllBranchesExit
	 *
	 * @return void
	 */
	public function testAllBranchesExit() {
		$code = '<?php

		switch($detail->type) {
			case \'date\':   return 1;
			case \'currency\':
			case \'dollar\': return 2;
			case \'state\':
				$countryId = ($specialFields[\'country\']>0 ? cleanCountry($mainDb, $row[ $specialFields[\'country\'] ] ) : $countryId );
				return 3;
			break;
			case \'gender\': return 4;
			case \'ssn\':    return 5;
			case \'phone\':  return 6;
			case \'country\':return 7;
			case \'marital_status\': return 8;
			case \'status\': return 9;
			case \'pay_group\': return 10;
			case \'twitter_handle\': // default
			case \'contact_url\': //contact_url uses the default, but I wanted to specify it in this list.
			case \'employee_access\': return 11;
			default:       return $detail->type;
		}
		';

		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();

		$output->expects($this->never())->method("emitError");

		$emptyTable = new InMemorySymbolTable(__DIR__);

		$stmts = self::parseText($code);
		$check = new SwitchCheck($emptyTable, $output);
		$this->assertTrue($check->allBranchesExit( $stmts ) );
	}

	/**
	 * testMissingBreak
	 *
	 * @return void
	 */
	public function testMissingBreak() {
		$code = '<?php
			switch($foo) {
				case 0:
				case 5: // Empty, but with comment (This is ok)
				case 1:
					echo "Error!\n";
					// Comment
				case 2:
					echo "Another error, but no comment\n";
				case 2:
					echo "Not error\n";
					break;
				case 3:
					// Last case, also not an error
			}
		';


		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();

		$output->expects($this->exactly(2))->method("emitError")->withConsecutive(
			[
				$this->anything(), $this->anything(),
				$this->equalTo(5),
				$this->stringContains(SwitchCheck::TYPE_MISSING_BREAK),
				$this->anything()
			],
			[
				$this->anything(), $this->anything(),
				$this->equalTo(8),
				$this->stringContains(SwitchCheck::TYPE_MISSING_BREAK),
				$this->anything()
			]
		);

		$emptyTable = new InMemorySymbolTable(__DIR__);

		$stmts = self::parseText($code);
		$check = new SwitchCheck($emptyTable, $output);
		$check->run(__FILE__, $stmts[0], null, null);
	}

	/**
	 * testGoodSwitch
	 *
	 * @return void
	 */
	public function testGoodSwitch() {
		$code = '<?php
			switch($size) {
				case \'small\': $size=1; $originalWidth=$originalHeight=$width=$height=150; break;
				case \'tiny\' : $size=1; $originalWidth=$originalHeight=150; $width=$height=20; break;
				case \'original\': $size=0; break;
				case \'large\': $size=2; break;
				case \'xs\': $size=3; break; // xs is our tiny of 50 by 50
				case \'medium\': $size=4; break;
				default:
					$response->responseCodeHeader(404,"Not found");
					$response->errorMessage("size not found must be small or tiny");
					return true;
			}
			';

		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();

		$output->expects($this->never())->method("emitError");

		$emptyTable = new InMemorySymbolTable(__DIR__);

		$stmts = self::parseText($code);
		$check = new SwitchCheck($emptyTable, $output);
		$check->run(__FILE__, $stmts[0], null, null);
	}
}