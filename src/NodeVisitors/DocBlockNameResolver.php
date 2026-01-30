<?php

namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2024, BambooHR
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Exceptions\DocBlockParserException;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\TypeParser;
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

	private TypeParser $parser;

	function __construct($context) {
		$this->context = $context;
		$this->parser = new TypeParser(fn($fn)=>$this->resolveTypeName($fn));
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
			$this->importFunctionValues($node);
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
		if ($comment && preg_match_all('/@var +([-A-Z0-9_|\\\\<>]+(?:\[])*)( +\\$([A-Z0-9_]+))?/i', $comment->getText(), $matchArray, PREG_SET_ORDER)) {
			$node->setAttribute("namespacedInlineVar", $this->buildVarsFromTag($matchArray));
		}
	}

	private function processDocBlockParams(Node\FunctionLike $node, string $comment) {
		if ($comment && preg_match_all('/@param +([-A-Z0-9_|\\\\<>[\\]]+)(?: +\\$([A-Z0-9_]+))?/i', $comment, $matchArray, PREG_SET_ORDER)) {
			$params = [];

			foreach ($matchArray as $tag) {
				if (isset($tag[2])) {
					$str = strval($tag[1]);
					try {
						if ($str != "type" && $str != "name") {
							$params[$tag[2]] = $this->parser->parse($str);
						}
						//echo "Set docblock : ".$tag[2]." ".$str."\n";
					} catch (DocBlockParserException) {
						//Ignore
					}
				}
			}

			foreach ($node->getParams() as $param) {
				if (is_string($param->var->name)) {
					$name = $param->var->name;
					if (isset($params[$name])) {
						$param->setAttribute('DocBlockName', $params[$name]);
					}
				}
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
		if ($comment && preg_match('/@var +([-A-Z0-9_|\\\\()]+)/i', $comment, $matchArray)) {
				try {
					$prop->props[0]->setAttribute("namespacedType", $this->parser->parse($matchArray[1]));
				} catch (DocBlockParserException $exception) {
					// Skip it.
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
	private function importFunctionValues($node) {
		$comment = $node->getDocComment();
		if ($comment) {
			$str = $comment->getText();
			try {
				$this->processDocBlockParams($node, $str);
				$this->processDocBlockTemplates($node, $str);
				$this->processDocBlockReturn($node, $str);
				$this->processDockBlockThrows($node, $str);
			} catch (\InvalidArgumentException $exception) {
				// Skip it.
			}
		}
	}

	private function resolveTypeName($type) {
		return $this->context->getResolvedClassName(new Name($type));
	}

	/**
	 * processDockBlockReturn
	 *
	 * @param Function_|ClassMethod $node Instance of FunctionAbstraction ClassMethod
	 * @param string                $str  The docBlock text
	 *
	 * @return void
	 */
	private function processDocBlockTemplates($node, $str) {
		/*
		if ($str && preg_match_all('/@template +([A-Z]+)\w*\$/i', $str, $matchArray, PREG_SET_ORDER)) {

			$returnType = strval($matchArray[0][1]);
			list($returnType) = explode(" ", $returnType, 2);
			if ($returnType != "" && strpos($returnType, "|") === false) {
				$returnType = $this->resolveTypeName($returnType);
				$node->setAttribute("namespacedReturn", strval($returnType));
			}
		}*/
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
		if ($str && preg_match('/@return +([-A-Z0-9_|\\\\<>,]+(\[])*)( +\\$([A-Z0-9_]+))?/i', $str, $matchArray)) {

			$returnType = $matchArray[1];
			try {
				$v = $this->parser->parse($returnType);
				$node->setAttribute("namespacedReturn", $v);
			} catch (DocBlockParserException) {
				// Ignore it.
			}
		}
	}

	private function processDockBlockThrows(Node $node, string $str) {
		if ($str && preg_match_all('/@throws +([A-Z0-9_\\\\]+)/i', $str, $matchArray, PREG_SET_ORDER)) {
			$list = [];
			foreach ($matchArray as $matches) {
				try {
					$list[] = $this->parser->parse($matches[0]);
				} catch (DocBlockParserException) {
					// Ignore
				}
			}
			$node->setAttribute("throws", $list);
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
				try {
					$vars[$tag[3]] = $this->parser->parse($str);
				} catch (DocBlockParserException) {
					//Ignore
				}
			}
		}
		return $vars;
	}


}