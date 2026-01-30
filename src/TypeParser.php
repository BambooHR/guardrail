<?php 

namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2024, BambooHR
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Exceptions\DocBlockParserException;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\UnionType;

class TypeParser {

	function __construct(private \closure $resolver) { }

	private function adjustTypeString($type) {
		// Clean up a few DocBlock eccentricities.
		if ($type == 'mixed') {
			$type = '';
		}
		if ($type == 'boolean') {
			$type = 'bool';
		}

		return $type;
	}

	private function generateNameOrIdentifier($type, array $templateVars=[]) {
		if ($type && strval($type) != "") {
			if (Util::isLegalNonObject($type) || Util::isSelfOrStaticType($type)) {
				return new Node\Identifier($type);
			} elseif (str_starts_with($type, "\\" )) {
				return new Name\FullyQualified(substr($type, 1), ["templates" => $templateVars]);
			} elseif ($type == "T" || $type == "class-string") {
				return new Name\FullyQualified($type, ["templates" => $templateVars]);
			} else {
				$var = call_user_func($this->resolver, new Name($type));
				if (count($templateVars)) {
					$var->setAttribute('templates', $templateVars);
				}
				return $var;
			}
		}
		return null;
	}

	/**
	 *
	 * Recognizes the following patterns: Type, Type[], Type[][], Type<T>, Type<T,T2>
	 * @param string $type
	 * @param int    $i
	 * @return Name|Identifier|null
	 * @throws DocBlockParserException
	 */
	private function parseString(string $type, int &$i): Name|Node\Identifier|null {
		$this->skipWs($type, $i);
		if (preg_match("/^([-A-Z0-9_\\\\]+)(?:((?:\[])+)|<([\\\\A-Z0-9_]+(,[\\\\A-Z0-9_]+)*)>)?/i", substr($type, $i), $matches, 0)) {
			$i += strlen($matches[0]);
			$name = $this->adjustTypeString($matches[1]);
			if (!empty($matches[2])) {
				if (!Config::shouldUseDocBlockTypedArrays()) {
					return null;
				}
				$depth = substr_count($matches[2], "[]");
				$leafType = new Identifier("array", ["templates" => [$this->generateNameOrIdentifier($name)]]);
				if ($depth == 1) {
					return $leafType;
				} else {
					$parent = $leafType;
					for ($parents = $depth - 1; $parents > 0; $parents--) {
						$parent = new Identifier("array", ["templates" => [$parent]]);
					}
				}
				return $parent;
			} elseif (!empty($matches[3])) {
				$templateVars = array_map($this->generateNameOrIdentifier(...), explode(",", $matches[3]));
			} else {
				$templateVars = [];
			}
			$ret = $this->generateNameOrIdentifier($name, $templateVars);
			return $ret;
		}
		throw new DocBlockParserException("Invalid type name: \"$type\"");
	}

	private function skipWs(string $type, int &$i): void {
		while ($i < strlen($type) && $type[$i] == ' ') {
			$i++;
		}
	}

	private function parseIntersection(string $type, int &$i): IntersectionType|Name|Node\Identifier|null {
		$this->skipWs($type, $i);
		if ($i < strlen($type)) {
			if ($type[$i] == '(') {
				++$i;
				$intersection = [$this->parseString($type, $i)];

				while ($i < strlen($type)) {
					$this->skipWs($type, $i);
					if ($type[$i] !== "&") {
						throw new DocBlockParserException("Expected \"&\" parsing \"$type\"");
					}
					++$i;
					$intersection[] = $this->parseString($type, $i);
					$this->skipWs($type, $i);
					if ($type[$i] == ")") {
						$i++;
						return new IntersectionType($intersection);
					}
				}
			} else {
				return $this->parseString($type, $i);
			}
		}
		throw new DocBlockParserException("Invalid type name: \"$type\"");
	}

	function parse(string $type): Name|Identifier|Node\ComplexType|null {
		$i = 0;
		$this->skipWs($type, $i);

		$intType = $this->parseIntersection($type, $i);
		$this->skipWs($type, $i);
		if ($i >= strlen($type)) {
			return $intType;
		} elseif ($type[$i] != "|") {
			throw new DocBlockParserException("Expected \"|\" in type name in \"$type\"");
		}
		$types = [ $intType ];
		while ($i < strlen($type) && $type[$i] == "|") {
			$i++;
			$types[] = $this->parseIntersection($type, $i);
		}

		if ($i >= strlen($type)) {
			return new UnionType($types);
		} else {
			throw new DocBlockParserException();
		}
	}
}