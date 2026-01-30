<?php

namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\EnumCodeAugmenter;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class Grabber
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class Grabber extends NodeVisitorAbstract {
	const FROM_NAME = 1;
	const FROM_FQN = 2;

	/**
	 * @var
	 */
	private $searchingForName;

	/**
	 * @var null
	 */
	private $foundClass = null;

	/**
	 * @var string
	 */
	private $classType = Class_::class;

	/**
	 * @var string
	 */
	private $fromVar = "fqn";

	/**
	 * Grabber constructor.
	 *
	 * @param string $searchingForName The search string
	 * @param string $classType        The class type
	 * @param int    $fromVar          The variable for from type
	 */
	public function __construct($searchingForName = "", $classType = Class_::class, $fromVar = self::FROM_FQN) {
		if ($searchingForName) {
			$this->initForSearch($searchingForName, $classType, $fromVar);
		}
	}

	/**
	 * initForSearch
	 *
	 * @param string $searchingForName The search string
	 * @param string $classType        The class type
	 * @param int    $fromVar          The variable for from type
	 *
	 * @return void
	 */
	public function initForSearch($searchingForName, $classType = Class_::class, $fromVar = "fqn") {
		$this->searchingForName = $searchingForName;
		$this->classType = $classType;
		$this->foundClass = null;
		$this->fromVar = $fromVar;
	}

	/**
	 * getFoundClass
	 *
	 * @return Class_|null
	 */
	public function getFoundClass() {
		return $this->foundClass;
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 * @guardrail-ignore Standard.Unknown.Property\
	 */
	public function enterNode(Node $node) {
		$class = $node::class;
		if (
			strcasecmp($class, $this->classType) == 0 ||
			(strcasecmp($class, Node\Stmt\Enum_::class) == 0 && strcasecmp($this->classType, Class_::class) == 0)
		) {
			$var = ($this->fromVar == self::FROM_FQN ? strval($node->namespacedName) : strval($node->name));
			if (strcasecmp($var, $this->searchingForName) == 0) {
				$this->foundClass = $node;
				return NodeTraverser::STOP_TRAVERSAL;
			}
		}
		return null;
	}

	/**
	 * filterByType
	 *
	 * @param array  $stmts List of statements
	 * @param string $type  The type
	 *
	 * @return array
	 */
	static public function filterByType($stmts, string|array $type) {
		$ret = [];
		if (is_string($type)) {
			$type = [$type];
		}
		foreach ($stmts as $stmt) {
			if (in_array(get_class($stmt), $type)) {
				$ret[] = $stmt;
			}
		}
		return $ret;
	}

	/**
	 * Note: The entire file must first be run through the NameResolver before searching for classes inside of the
	 * statements array.
	 *
	 * @param SymbolTable $table     Instance of SymbolTable
	 * @param array       $stmts     The list of statements
	 * @param string      $className The class name
	 * @param string      $classType The class type
	 * @param int         $fromVar   The type of from
	 *
	 * @return null|Class_|Interface_|Trait_
	 */
	static public function getClassFromStmts(SymbolTable $table, $stmts, $className, $classType = Class_::class, $fromVar = self::FROM_FQN) {
		$grabber = new Grabber($className, $classType, $fromVar);
		$traverser = new NodeTraverser;
		$traverser->addVisitor($grabber);
		$traverser->traverse($stmts);
		return $grabber->getFoundClass();
	}

	/**
	 * getClassFromFile
	 *
	 * @param SymbolTable $table     Instance of the SymbolTable
	 * @param string      $fileName  The file name
	 * @param string      $className The class name
	 * @param string      $classType The class type
	 *
	 * @return Interface_|Trait_|null|Class_
	 */
	static public function getClassFromFile(SymbolTable $table, $fileName, $className, $classType = Class_::class) {
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
			$traverser->addVisitor($resolver = new NameResolver());
			$traverser->addVisitor(new DocBlockNameResolver($resolver->getNameContext()));
			$traverser->addVisitor(new PromotedPropertyVisitor());
			$stmts = $traverser->traverse($stmts);

			if ($classType == Class_::class) {
				try {
					$traverser = new NodeTraverser;
					$traverser->addVisitor(new TraitImportingVisitor($table));
					$stmts = $traverser->traverse($stmts);
				} catch (UnknownTraitException $exception) {
					echo "[$className] Unknown trait! " . $exception->getMessage() . "\n";
					// Ignore these for now.
				}
			}

			$lastFile = $fileName;
			$lastContents = $stmts;
		}

		if ($stmts) {
			$cls = self::getClassFromStmts($table, $stmts, $className, $classType);
			if ($cls instanceof Node\Stmt\Enum_) {
				EnumCodeAugmenter::addEnumPropsAndMethods($cls);
			}
			return $cls;
		}
		return null;
	}
}
