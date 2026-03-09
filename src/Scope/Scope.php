<?php

namespace BambooHR\Guardrail\Scope;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
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
	
	/**
	 * @var int Version counter for tracking when variables are defined in this scope
	 */
	private static $globalScopeVersion = 0;
	
	/**
	 * @var int The version of this scope (incremented for each branch)
	 */
	private $scopeVersion = 0;
	
	function __construct(public bool $isStatic, public bool $isGlobal, public bool $isStrict, private ?FunctionLike $inside = null) {
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
			$var->scopeVersion = $this->scopeVersion; // Mark when this variable was defined
			// Set mayBeNull based on type: untyped or ?Type or Type|null = true
			$var->mayBeNull = \BambooHR\Guardrail\TypeComparer::isTypeNullable($type);
			// Variable is being defined, so it's not unset
			$var->mayBeUnset = false;
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
		
		// Increment scope version so we can track variables defined in this branch
		self::$globalScopeVersion++;
		$ret->scopeVersion = self::$globalScopeVersion;
		
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
	
	/**
	 * Merge multiple branch scopes, handling early exits and undefined variables
	 * 
	 * @param Scope[] $branches Array of branch scopes to merge (including implicit branches)
	 * @param int[] $exitedBranches Indices of branches that exited early (return/throw/break)
	 * @param bool $hasImplicitBranch Deprecated - implicit branches should be added to $branches array
	 * @return void
	 */
	public function mergeBranches(array $branches, array $exitedBranches = [], bool $hasImplicitBranch = false): void {
		// Filter out branches that exited early
		$completedBranches = [];
		foreach ($branches as $index => $branch) {
			if (!in_array($index, $exitedBranches, true)) {
				$completedBranches[] = $branch;
			}
		}
		
		// If no branches completed (all exited early), nothing to merge
		if (empty($completedBranches)) {
			return;
		}
		
		// Collect all variables from all branches
		$allVarNames = [];
		foreach ($completedBranches as $branch) {
			foreach ($branch->vars as $name => $var) {
				$allVarNames[$name] = true;
			}
		}
		
		// For each variable, merge types from all branches
		foreach (array_keys($allVarNames) as $varName) {
			$types = [];
			$existsInAllBranches = true;
			$mayBeNullInAny = false;
			$mayBeUnsetInAny = false;
			
			$branchesWithVar = 0;
			$branchesWhereVarWasNew = 0;
			
			foreach ($completedBranches as $branchIdx => $branch) {
				if ($branch->getVarExists($varName)) {
					$branchVar = $branch->getVarObject($varName);
					if ($branchVar) {
						// Check if this variable was newly defined in THIS branch
						$wasNewInThisBranch = ($branchVar->scopeVersion > 0 && $branchVar->scopeVersion == $branch->scopeVersion);
						
						// Check if this variable was created in a DIFFERENT branch (not inherited from parent)
						// A variable is inherited if its scopeVersion is less than the branch's scopeVersion
						// A variable was created in a different branch if its scopeVersion is greater than the branch's scopeVersion
						$wasCreatedInDifferentBranch = false;
						if ($branchVar->scopeVersion > 0 && $branchVar->scopeVersion > $branch->scopeVersion) {
							// This variable has a scope version NEWER than this branch
							// It was created in a different branch and leaked into this scope
							$wasCreatedInDifferentBranch = true;
						}
						// Note: If scopeVersion < branch->scopeVersion, it's inherited from parent (not created in different branch)
						
						// Only count this variable if it wasn't created in a different branch
						if (!$wasCreatedInDifferentBranch) {
							$branchesWithVar++;
							$types[] = $branchVar->type;
							$mayBeNullInAny = $mayBeNullInAny || $branchVar->mayBeNull;
							$mayBeUnsetInAny = $mayBeUnsetInAny || $branchVar->mayBeUnset;
							
							if ($wasNewInThisBranch) {
								$branchesWhereVarWasNew++;
							}
						} else {
							// Variable was created in a different branch, treat as not existing in this branch
							$existsInAllBranches = false;
						}
					}
				} else {
					$existsInAllBranches = false;
				}
			}
			
			// If variable doesn't exist in all branches, OR if it was newly defined in some 
			// branches but not all, then it may be unset
			if (!$existsInAllBranches || ($branchesWhereVarWasNew > 0 && $branchesWhereVarWasNew < count($completedBranches))) {
				$mayBeUnsetInAny = true;
			}
			
			// Union all types together
			$mergedType = $this->unionTypes($types);
			
			// Set or update the variable in this scope
			if (!isset($this->vars[$varName])) {
				$var = new ScopeVar();
				$var->name = $varName;
				$this->vars[$varName] = $var;
			}
			
			$this->vars[$varName]->type = $mergedType;
			$this->vars[$varName]->typeChanged = true;
			$this->vars[$varName]->mayBeNull = $mayBeNullInAny;
			$this->vars[$varName]->mayBeUnset = $mayBeUnsetInAny;
		}
	}
	
	/**
	 * Create a union type from multiple types
	 * 
	 * @param array $types Array of type nodes
	 * @return Name|Identifier|ComplexType|null
	 */
	private function unionTypes(array $types): Name|Identifier|ComplexType|null {
		// Filter out null types
		$types = array_filter($types, fn($t) => $t !== null);
		
		if (empty($types)) {
			return null;
		}
		
		if (count($types) === 1) {
			return reset($types);
		}
		
		// Flatten any existing union types
		$flatTypes = [];
		foreach ($types as $type) {
			if ($type instanceof ComplexType && $type instanceof Node\UnionType) {
				foreach ($type->types as $subType) {
					$flatTypes[] = $subType;
				}
			} else {
				$flatTypes[] = $type;
			}
		}
		
		// Remove duplicates by comparing type strings
		$uniqueTypes = [];
		$seenTypes = [];
		foreach ($flatTypes as $type) {
			$typeStr = TypeComparer::typeToString($type);
			if (!isset($seenTypes[$typeStr])) {
				$seenTypes[$typeStr] = true;
				$uniqueTypes[] = $type;
			}
		}
		
		if (count($uniqueTypes) === 1) {
			return $uniqueTypes[0];
		}
		
		return new Node\UnionType($uniqueTypes);
	}
}
