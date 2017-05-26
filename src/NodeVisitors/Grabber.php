<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

class Grabber extends NodeVisitorAbstract {
	const FROM_NAME = 1;
	const FROM_FQN = 2;

	private $searchingForName;
	private $foundClass = null;
	private $classType = Class_::class;
	private $fromVar = "fqn";

	function __construct( $searchingForName="", $classType=Class_::class, $fromVar=self::FROM_FQN ) {
		if ($searchingForName) {
			$this->initForSearch($searchingForName, $classType, $fromVar);
		}
	}

	function initForSearch( $searchingForName, $classType=Class_::class, $fromVar="fqn") {
		$this->searchingForName = $searchingForName;
		$this->classType = $classType;
		$this->foundClass = null;
		$this->fromVar = $fromVar;
	}

	/**
	 * @return Class_|null
	 */
	function getFoundClass() {
		return $this->foundClass;
	}

	function enterNode(Node $node) {
		if (strcasecmp(get_class($node), $this->classType) == 0) {

			$var = ($this->fromVar == self::FROM_FQN ? strval($node->namespacedName) : strval($node->name));
			if (strcasecmp($var, $this->searchingForName) == 0) {
				$this->foundClass = $node;

			}
		}
	}

	static function filterByType($stmts, $type) {
		$ret = [];
		foreach ($stmts as $stmt) {
			if (get_class($stmt) == $type) {
				$ret[] = $stmt;
			}
		}
		return $ret;
	}

	/**
	 * Note: The entire file must first be run through the NameResolver before searching for classes inside of the
	 * statements array.
	 *
	 * @param     $stmts
	 * @param     $className
	 * @param     $classType
	 * @param int       $fromVar
	 * @return null|Class_|Interface_|Trait_
	 */
	static function getClassFromStmts( SymbolTable $table, $stmts, $className, $classType=Class_::class, $fromVar=self::FROM_FQN) {
		$grabber = new Grabber($className, $classType, $fromVar);
		$traverser = new NodeTraverser;
		$traverser->addVisitor($grabber);
		$traverser->traverse( $stmts );

		return $grabber->getFoundClass();
	}

	static function getClassFromFile( SymbolTable $table, $fileName, $className, $classType=Class_::class ) {
		static $lastFile = "";
		static $lastContents;
		if ($lastFile == $fileName) {
			$stmts = $lastContents;
		} else {
			$contents = file_get_contents($fileName);
			$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
			try {
				$stmts = $parser->parse($contents);
			} catch (Error $error) {
				echo "Error parsing: $contents\n";
				return null;
			}

			$traverser = new NodeTraverser;
			$traverser->addVisitor(new DocBlockNameResolver());
			$stmts = $traverser->traverse( $stmts );

			if ($classType == Class_::class) {
				try {
					$traverser = new NodeTraverser;
					$traverser->addVisitor(new TraitImportingVisitor($table));
					$stmts = $traverser->traverse($stmts);
				} catch (UnknownTraitException $e) {
					echo "Unknown trait! " . $e->getMessage() . "\n";
					// Ignore these for now.
				}
			}

			$lastFile = $fileName;
			$lastContents = $stmts;
		}

		if ($stmts) {
			return self::getClassFromStmts($table, $stmts, $className, $classType);
		}
		return null;
	}
}

