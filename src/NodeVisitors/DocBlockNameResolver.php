<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitor\NameResolver;
use BambooHR\Guardrail\Abstractions\ClassMethod;

/**
 * Class DocBlockNameResolver
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class DocBlockNameResolver extends NameResolver {

	/**
	 * @var array
	 */
	private $classAliases = [];

	/**
	 * @var bool
	 */
	private $useDocBlock = true;

	/**
	 * DocBlockNameResolver constructor.
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * addAlias
	 *
	 * @param UseUse    $use    Instance of UseUse
	 * @param string    $type   A constant (TYPE_*) from UseUse
	 * @param Name|null $prefix Instance of name (or null)
	 *
	 * @return void
	 */
	protected function addAlias(UseUse $use, $type, Name $prefix = null) {
		parent::addAlias($use, $type, $prefix);
		if ($type == Stmt\Use_::TYPE_NORMAL) {
			// Add prefix for group uses
			$name = strval($prefix ? Name::concat($prefix, $use->name) : $use->name);
			$this->classAliases[$use->alias] = $name;
		}
	}
	/**
	 * enterNode
	 *
	 * @param Node $node Instance of node
	 *
	 * @return void
	 */
	public function enterNode(Node $node) {
		if ($this->useDocBlock) {
			if ($node instanceof Function_ || $node instanceof \PhpParser\Node\Stmt\ClassMethod) {
				$this->importReturnValue($node);
			}
			if ($node instanceof Property) {
				$this->importVarType($node);
			} else {
				$this->importInlineVarType($node);
			}
		}
		parent::enterNode($node);
	}

	/**
	 * @param Node $node -
	 * @return void
	 */
	function importInlineVarType(Node $node) {
		$comment = $node->getDocComment();
		if ($comment && preg_match_all('/@var +([-A-Z0-9_|\\\\]+)( +\\$([A-Z0-9_]+))?/i', $comment->getText(), $matchArray, PREG_SET_ORDER)) {
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
	public function importVarType(Property $prop) {
		$comment = $prop->getDocComment();
		if ($comment && preg_match_all('/@var +([-A-Z0-9_|\\\\]+)( +(\\$[A-Z0-9_]+))?/i', $comment, $matchArray, PREG_SET_ORDER)) {
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
	function importReturnValue($node) {
		$comment = $node->getDocComment();
		if ($comment) {
			$str = $comment->getText();
			try {
				$this->processDockBlockReturn($node, $str);
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
				if ($type=='mixed') {
					$type = '';
				}
				// Ignore union types.
				if ($type && strval($type) != "" && strpos($type,'\\') !== 0 && strpos($type, "|") === false && !Util::isLegalNonObject($type)) {
					$resolvedName = $this->getNameContext()->getResolvedClassName( new Name($type) );
					if ($resolvedName) {
						$type = strval($resolvedName);
					}
					if ($type[0] == '\\') {
						$type = substr($type, 1);
					}
					$prop->props[0]->setAttribute("namespacedType", $type);
				}
			}
		}
	}

	/**
	 * processDockBlockReturn
	 *
	 * @param Function_|ClassMethod $node Instance of FunctionAbstraction ClassMethod
	 * @param string                $str  The docBlock text
	 *
	 * @return void
	 */
	private function processDockBlockReturn($node, $str) {
		if ($str && preg_match_all('/@return +([A-Z0-9_|\\\\]+)/i', $str, $matchArray, PREG_SET_ORDER)) {
			$returnType = strval($matchArray[0][1]);
			list($returnType) = explode(" ", $returnType, 2);
			if ($returnType != "" && strpos($returnType, "|") === false) {

				if ($returnType == "mixed") {
					$returnType = "";
				}
				if ($returnType && strval($returnType) != "" && strpos($returnType, '\\') !== 0 && !Util::isLegalNonObject($returnType)) {
					$resolvedType = $this->getNameContext()->getResolvedClassName( new Name($returnType) );
					if($resolvedType) {
						$returnType = strval($resolvedType);
					}
				}
				if (substr($returnType, 0, 1) == "\\") {
					$returnType = substr($returnType, 1);
				}
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
				$type = strval($tag[1]);

				if(strpos($type, '\\') !== 0 && !Util::isLegalNonObject($type)) {
					$resolvedName = $this->getNameContext()->getResolvedClassName( new Name($type) );
					if ($resolvedName) {
						$type = strval($resolvedName);
					}
				}

				if ($type && $type[0] == "\\") {
					$type = substr($type, 1);
				}

				if ($type != "type") {
					$vars[$tag[3]] = Scope::nameFromConst($type);
				}
			}
		}
		return $vars;
	}
}