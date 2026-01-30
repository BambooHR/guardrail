<?php

namespace BambooHR\Guardrail\Scope;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node\ComplexType;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Class Scope
 *
 * @package BambooHR\Guardrail
 */
class Scope implements PluginScopeInterface {
	/**
	 * @var ScopeVar[]
	 */
	private $vars = [];

	function __construct(
		public bool $isStatic,
		public bool $isGlobal,
		public bool $isStrict,
		private ?FunctionLike $inside = null
	) {
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic(): bool {
		return $this->isStatic;
	}

	public function isStrict(): bool {
		return $this->isStrict;
	}

	/**
	 * isGlobal
	 *
	 * @return bool
	 */
	public function isGlobal(): bool {
		return $this->isGlobal;
	}

	/**
	 * getInsideFunction
	 *
	 * @return FunctionLike
	 */
	public function getInsideFunction(): ?FunctionLike {
		return $this->inside;
	}

	/**
	 * setVarType
	 *
	 * @param string $name The name
	 * @param string $type The type
	 * @param int    $line Line number
	 *
	 * @return void
	 */
	public function setVarType($name, Name|Identifier|ComplexType|null $type, $line): void {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$this->vars[$name] = $var;
		}

		$var = $this->vars[$name];
		$var->type = $type;
		$var->typeChanged = true;
		$var->modifiedLine = $line;
		if (str_contains($name, "->")) {
			// Assigning a shorter variable, means the longer chains are invalidated.
			// IE: $a->b->c invalidates $a->b->c->d
			$name .= "->";
			foreach (array_keys($this->vars) as $varName) {
				if (str_starts_with($varName, $name)) {
					unset($this->vars["name"]);
				}
			}
		}
	}


	/**
	 * Used to inject an existing variable into a different scope.  Used for references in uses() clauses.
	 * @param string   $name -
	 * @param ScopeVar $ref  -
	 * @return void
	 */
	public function setVarReference($name, ScopeVar $ref): void {
		$this->vars[$name] = $ref;
	}

	/**
	 * setVarWritten
	 *
	 * @param string $name The name
	 * @param int    $line The line number
	 *
	 * @return void
	 */
	public function setVarWritten($name, $line): void {

		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$this->vars[$name] = $var;
		}
		$this->vars[$name]->modified = true;
		$this->vars[$name]->modifiedLine = $line;
	}

	/**
	 * setVarUsed
	 *
	 * @param string $name The name of the item
	 *
	 * @return void
	 */
	public function setVarUsed($name): void {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$this->vars[$name] = $var;
		}

		$this->vars[$name]->used = true;
	}


	/**
	 * @return void
	 */
	public function dump($typeChanged = false, $used = false, $modified = false): void {
		if (!$typeChanged && !$used && !$modified) {
			$typeChanged = $used = $modified = true;
		}
		echo "Scope: " . "TYPES: " . intval($typeChanged) . " USED: " . intval($used) . " MODIFIED: " . intval($modified) . " " . ($this->getInsideFunction() && get_class($this->getInsideFunction())) . "\n";
		foreach ($this->vars as $name => $var) {
			if (($typeChanged && $var->typeChanged) || ($used && $var->used) || ($modified && $var->modified)) {
				echo "  Name $name, Type " . TypeComparer::typeToString($var->type) . " " . ($var->used ? "used" : "not used") . "\n";
			}
		}
		echo "\n";
	}

	/**
	 * markAllVarsUsed
	 *
	 * @return void
	 */
	public function markAllVarsUsed(): void {
		foreach ($this->vars as $var) {
			$var->used = true;
		}
	}

	/**
	 * getUnusedVars
	 *
	 * @return array
	 */
	public function getUnusedVars(): array {
		$ret = [];
		foreach ($this->vars as $key => $var) {
			if (!$var->used) {
				$ret[$key] = $var->modifiedLine;
			}
		}
		return $ret;
	}

	public function getUsedVars(): array {
		return array_filter($this->vars, fn($var)=>$var->used);
	}

	/**
	 * @return ScopeVar[]
	 */
	public function getTypeChangedVars(): array {
		$ret = [];
		foreach ($this->vars as $key => $var) {
			if ($var->typeChanged) {
				$ret[$key] = $var;
			}
		}
		return $ret;
	}

	/**
	 * getVarType
	 *
	 * @param string $name The name
	 *
	 * @return mixed|string
	 */
	function getVarType($name): Name|Identifier|ComplexType|null {
		return isset($this->vars[$name]) ? $this->vars[$name]->type : null;
	}

	function getVarExists(string $name): bool {
		return isset($this->vars[$name]);
	}


	/**
	 * getScopeClone
	 *
	 * @return Scope
	 */
	public function getScopeClone(): self {
		$newVars = [];
		foreach ($this->vars as $var) {
			$newVar = clone $var;
			$newVar->typeChanged = false;
			$newVars[$var->name] = $newVar;
		}
		$ret = new self($this->isStatic, $this->isGlobal, $this->isStrict, $this->inside);
		$ret->vars = $newVars;
		return $ret;
	}

	/**
	 * @param string $name The name of the object.
	 * @return ScopeVar|null
	 */
	function getVarObject($name): ?ScopeVar {
		return (isset($this->vars[$name]) ? $this->vars[$name] : null);
	}

	/**
	 * @param Scope $other -
	 * @return void
	 */
	public function merge(PluginScopeInterface $other): void {
		// See if any new vars were added to the scope or if existing ones were changed.
		foreach ($other->vars as $name => $otherVar) {
			if (!isset($this->vars[$name])) {
				$this->vars[$name] = $otherVar;
			} else {
				$this->vars[$name]->mergeVar($otherVar);
			}
		}
	}
}
