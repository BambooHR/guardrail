<?php

namespace BambooHR\Guardrail\SymbolTable;

use BambooHR\Guardrail\Exceptions\DocBlockParserException;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\TypeParser;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

class TypeStringTable implements \JsonSerializable {
	private array $strings = [];
	private array $ids = [];
	static ?TypeParser $parser = null;

	function __construct() {
		self::$parser = new TypeParser(fn($typeName)=>new Name\FullyQualified(strval($typeName)));
	}

	function add(ComplexType|Identifier|Name $type): int {
		//$type = TypeComparer::normalizeType($type);
		$typeString = TypeComparer::typeToString($type);

		if (!isset($this->strings[$typeString])) {
			$count = count($this->strings) + 1;
			$this->strings[$typeString] = $count;
			$this->ids[$count] = $typeString;
		}
		return $this->strings[$typeString];
	}

	function getString(int $index): ComplexType|Name|Identifier {
		if (strcasecmp($this->ids[$index], "mixed") == 0) {
			return TypeComparer::identifierFromName("mixed");
		}
		try {
			$type = self::$parser->parse($this->ids[$index]);
		} catch (DocBlockParserException $ex) {
			return new Identifier("mixed");
		}
		if (!$type) {
			echo "Unable to parse: " . $this->ids[$index] . "\n";
			return new Identifier("mixed");
		}
		return $type;
	}

	#[\ReturnTypeWillChange]
	function jsonSerialize() {
		return $this->strings;
	}

	static function fromArray($arr): TypeStringTable {
		$ret = new TypeStringTable();
		$ret->strings = $arr;
		$ret->ids = array_flip($arr);
		return $ret;
	}
}
