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

	/**
	 * BaseCheck constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of the OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		$this->symbolTable = $symbolTable;
		$this->doc = $doc;
	}

	/**
	 * emitError
	 *
	 * @param string $file    The file
	 * @param Node   $node    Instance of the Node
	 * @param string $class   The name of the class
	 * @param string $message The message
	 *
	 * @return mixed
	 */
	public function emitError($file, \PhpParser\Node $node, $class, $message="") {
		$trait = $node->getAttribute("importedFromTrait");
		if ($trait) {
			$trait = str_replace("//", "/", $trait);
			$message .= " in imported code " . $this->symbolTable->removeBasePath($trait) . ":" . $node->getLine();
			return $this->emitErrorOnLine($file, $node->getAttribute('importedOnLine'), $class, $message);
		} else {
			return $this->emitErrorOnLine($file, $node->getLine(), $class, $message);
		}
	}

	/**
	 * emitErrorOnLine
	 *
	 * @param string $file       The file name
	 * @param int    $lineNumber The line number
	 * @param string $class      The class
	 * @param string $message    The message
	 *
	 * @return mixed
	 */
	public function emitErrorOnLine($file, $lineNumber, $class, $message="") {
		return $this->doc->emitError(get_class($this), $file, $lineNumber, $class, $message);
	}

	/**
	 * incTests
	 *
	 * @return void
	 */
	public function incTests() {
		$this->doc->incTests();
	}

	/**
	 * getCheckNodeTypes
	 *
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
	 * @return void
	 */
	abstract public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null);
}