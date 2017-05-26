<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;


use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\Types\Context;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitor\NameResolver;
use BambooHR\Guardrail\Abstractions\ClassMethod;

class DocBlockNameResolver extends NameResolver {
	private $factory;
	private $classAliases = [];
	private $useDocBlock = true;

	function __construct() {
		$this->factory  = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
	}

	protected function addAlias(Stmt\UseUse $use, $type, Name $prefix = null) {
		parent::addAlias($use, $type, $prefix);
		if ($type == Stmt\Use_::TYPE_NORMAL) {
			// Add prefix for group uses
			$name = strval( $prefix ? Name::concat($prefix, $use->name) : $use->name );
			$this->classAliases[$use->alias] = $name;
		}
	}


	protected function resetState(Name $namespace = null) {
		parent::resetState($namespace);
		$this->classAliases = [];
	}

	function enterNode(\PhpParser\Node $node) {
		if ($this->useDocBlock) {
			if ($node instanceof Function_ || $node instanceof \PhpParser\Node\Stmt\ClassMethod) {
				$this->importReturnValue($node);
			}
			if ($node instanceof Property) {
				$this->importVarType($node);
			}
		}
		parent::enterNode($node);

	}

	function getDocBlockContext() {
		return new Context( strval($this->namespace), $this->classAliases );
	}

	function importVarType(Property $prop) {
		$prop->getDocComment();
		$comment = $prop->getDocComment();
		if ($comment) {
			$str = $comment->getText();
			if (count($prop->props) >= 1) {
				try {
					$docBlock = $this->factory->create($str, $this->getDocBlockContext());

					/** @var Var_[] $types */
					$types = $docBlock->getTagsByName("var");
					if (count($types) > 0) {
						$type = strval($types[0]->getType());
						if (!empty($type)) {

							if ($type[0] == '\\') {
								$type = substr($type, 1);
							}
							$prop->props[0]->setAttribute("namespacedType", strval($type));
						}
					}
				} catch (\InvalidArgumentException $e) {
					// Skip it.
				}
			}
		}
	}

	/**
	 * @param Function_|ClassMethod $node
	 */
	function importReturnValue($node) {
		$comment = $node->getDocComment();
		if ($comment) {
			$str = $comment->getText();
			try {
				$docBlock = $this->factory->create($str, $this->getDocBlockContext());
				$return = $docBlock->getTagsByName("return");
				if (count($return)) {
					$returnType = $return[0]->getType();
					$types = explode("|", $returnType);
					if (count($types) > 1) {
						$node->setAttribute("namespacedReturn", \BambooHR\Guardrail\Scope::MIXED_TYPE);
					} else {
						foreach ($types as $type) {
							if ($type[0] == '\\') {
								$type = substr($type, 1);
							}
							$node->setAttribute("namespacedReturn", strval($type));
							return;
						}
					}
				}
			} catch (\InvalidArgumentException $e) {
				// Skip it.
			}
		}
	}
}