<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Context;
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
	 * @var DocBlockFactory
	 */
	private $factory;

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
		$this->factory  = DocBlockFactory::createInstance();
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
			$name = strval( $prefix ? Name::concat($prefix, $use->name) : $use->name );
			$this->classAliases[$use->alias] = $name;
		}
	}

	/**
	 * resetState
	 *
	 * @param Name|null $namespace Instance of Name (or null)
	 *
	 * @return void
	 */
	protected function resetState(Name $namespace = null) {
		parent::resetState($namespace);
		$this->classAliases = [];
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
			}
		}
		parent::enterNode($node);
	}

	/**
	 * getDocBlockContext
	 *
	 * @return Context
	 */
	public function getDocBlockContext() {
		return new Context( strval($this->namespace), $this->classAliases );
	}

	/**
	 * importVarType
	 *
	 * @param Property $prop Instance of Property
	 *
	 * @return void
	 */
	public function importVarType(Property $prop) {
		$prop->getDocComment();
		$comment = $prop->getDocComment();
		if ($comment) {
			$str = $comment->getText();
			if (count($prop->props) >= 1) {
				try {
					$this->getDocBlockAttributes($prop, $str);
				} catch (\InvalidArgumentException $exception) {
					// Skip it.
				}
			}
		}
	}

	/**
	 * importReturnValue
	 *
	 * @param Function_|ClassMethod $node Instance of Function_ ClassMethod
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
	private function getDocBlockAttributes(Property $prop, $str) {
		$docBlock = $this->factory->create($str, $this->getDocBlockContext());
		/** @var Var_[] $types */
		$types = $docBlock->getTagsByName("var");
		if (count($types) > 0) {
			$type = strval($types[0]->getType());
			if (! empty($type)) {
				if ($type[0] == '\\') {
					$type = substr($type, 1);
				}
				$prop->props[0]->setAttribute("namespacedType", strval($type));
			}
		}
}

	/**
	 * processDockBlockReturn
	 *
	 * @param Function_|ClassMethod $node Instance of Function_ ClassMethod
	 * @param string                $str  The docBlock text
	 *
	 * @return void
	 */
	private function processDockBlockReturn($node, $str) {
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
}
}