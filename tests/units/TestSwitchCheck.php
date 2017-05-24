<?php
use BambooHR\Guardrail\Checks\SwitchCheck;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use PhpParser\ParserFactory;

class TestSwitchCheck extends \PHPUnit_Framework_TestCase {

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
		$code = '<?

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

}