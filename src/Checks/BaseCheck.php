<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class BaseCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
abstract class BaseCheck extends ErrorConstants {

	/** @var SymbolTable */
	protected $symbolTable;

	/** @var \BambooHR\Guardrail\Output\OutputInterface  */
	private $doc;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		$this->symbolTable = $symbolTable;
		$this->doc = $doc;
	}

	function emitError($file, \PhpParser\Node $node, $class, $message="") {
		return $this->emitErrorOnLine($file, $node->getLine(), $class, $message);
	}

	function emitErrorOnLine($file, $lineNumber, $class, $message="") {
		return $this->doc->emitError(get_class($this), $file, $lineNumber, $class, $message);
	}

	function incTests() {
		$this->doc->incTests();
	}

	/**
	 * @return string[]
	 */
	abstract function getCheckNodeTypes();

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return mixed
	 */
	abstract public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null);
}