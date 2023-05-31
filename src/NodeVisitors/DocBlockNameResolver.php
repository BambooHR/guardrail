<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;

/**
 * Class DocBlockNameResolver
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class DocBlockNameResolver extends NodeVisitorAbstract {

	/** @var NameContext */
	private $context;

	function __construct($context) {
		$this->context = $context;
	}
	/**
	 * enterNode
	 *
	 * @param Node $node Instance of node
	 *
	 * @return void
	 */
	public function enterNode(Node $node) {
		parent::enterNode($node);
		if ($node instanceof Function_ || $node instanceof \PhpParser\Node\Stmt\ClassMethod) {
			$this->importReturnValue($node);
		}
		if ($node instanceof Property) {
			$this->importVarType($node);
		} else {
			$this->importInlineVarType($node);
		}
	}

	/**
	 * @param Node $node -
	 * @return void
	 */
	private function importInlineVarType(Node $node) {
		$comment = $node->getDocComment();
		if ($comment && preg_match_all('/@var +([-A-Z0-9_|\\\\]+(?:\[\])*)( +\\$([A-Z0-9_]+))?/i', $comment->getText(), $matchArray, PREG_SET_ORDER)) {
			$vars = $this->buildVarsFromTag($matchArray);

			if (count($vars) > 0) {
				$node->setAttribute("namespacedInlineVar", $vars);
			}
		}
	}


	/**
	 * importVarType
	 *
	 * @param Property $prop Instance of Property
	 *
	 * @return void
	 */
	private function importVarType(Property $prop) {
		$comment = $prop->getDocComment();
		if ($comment && preg_match_all('/@var +([-A-Z0-9_|\\\\]+(?:\[\])*)( +(\\$[A-Z0-9_]+))?/i', $comment, $matchArray, PREG_SET_ORDER)) {
			if (count($prop->props) >= 1) {
				try {
					$this->setDocBlockAttributes($prop, $matchArray);
				} catch (\InvalidArgumentException $exception) {
					// Skip it.
				}
			}
		}
	}

	/**
	 * importReturnValue
	 *
	 * @param Function_|ClassMethod $node Instance of FunctionAbstraction ClassMethod
	 *
	 * @return void
	 */
	private function importReturnValue($node) {
		$comment = $node->getDocComment();
		if ($comment) {
			$str = $comment->getText();
			try {
				$this->processDocBlockReturn($node, $str);
			} catch (\InvalidArgumentException $exception) {
				// Skip it.
			}
		}
	}

	/**
	 * getDocBlockAttributes
	 *
	 * @param Property $prop Instance of Property
	 * @param string   $str  The comment
	 *
	 * @return void
	 */
	private function setDocBlockAttributes(Property $prop, $matches) {
		if (count($matches) > 0) {
			$type = strval($matches[0][1]);
			if (!empty($type)) {
				$prop->props[0]->setAttribute("namespacedType", $this->resolveTypeName($type));
			}
		}
	}

	private function resolveTypeName($type) {
		if ($type=='mixed' || strpos($type,'|') !== false) {
			$type = '';
		}

		if (preg_match("/^([^\[]*)((?:\[\])+)\$/", $type, $matches)) {
			list(, $type,$parens)=$matches;
		} else {
			$parens = "";
		}

		// Ignore union types.
		if ($type && strval($type) != "" && strpos($type,'\\') !== 0 && !Util::isLegalNonObject($type)) {
			$resolvedName = $this->context->getResolvedClassName( new Name($type) );
			if ($resolvedName) {
				$type = strval($resolvedName);
			}
		}
		$type .= $parens;
		if ($type && $type[0] == '\\') {
			$type = substr($type, 1);
		}
		return $type;
	}

	/**
	 * processDockBlockReturn
	 *
	 * @param Function_|ClassMethod $node Instance of FunctionAbstraction ClassMethod
	 * @param string                $str  The docBlock text
	 *
	 * @return void
	 */
	private function processDocBlockReturn($node, $str) {
		if ($str && preg_match_all('/@return +([A-Z0-9_|\\\\]+(?:\[\])*)/i', $str, $matchArray, PREG_SET_ORDER)) {
			$returnType = strval($matchArray[0][1]);
			list($returnType) = explode(" ", $returnType, 2);
			if ($returnType != "" && strpos($returnType, "|") === false) {
				$returnType = $this->resolveTypeName($returnType);
				$node->setAttribute("namespacedReturn", strval($returnType));
			}
		}
	}

	/**
	 * @param array[] $tags array of arrays.  [[0=> whole match, 1=>type, 3=>optional name],...]
	 * @return array
	 */
	protected function buildVarsFromTag($tags) {
		$vars = [];
		foreach ($tags as $tag) {
			if (isset($tag[3])) {
				$str = strval($tag[1]);
				if(str_contains($str,'|')) {
					$vars[$tag[3]] = new Node\UnionType(array_map(
						fn($term)=>TypeComparer::nameFromName($this->resolveTypeName($term)),
						explode('|', $str)
					));
				} else {
					$type = $this->resolveTypeName($str);
					if ($type != "type") {
						$vars[$tag[3]] = TypeComparer::nameFromName($type);
					}
				}
			}
		}
		return $vars;
	}
}