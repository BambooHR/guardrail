<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Output\OutputInterface;

/**
 * Class BaseCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
abstract class BaseCheck extends BaseCheckConstants {

	/** @var SymbolTable */
	protected $symbolTable;

	/** @var \BambooHR\Guardrail\Output\OutputInterface  */
	private $doc;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		$this->symbolTable=$symbolTable;
		$this->doc=$doc;
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

	abstract function run($fileName, $node, Node\Stmt\ClassLike $inside=null, Scope $scope=null);
}