<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\FunctionLike;

/**
 * Class Scope
 *
 * @package BambooHR\Guardrail
 */
class Scope {
	const UNDEFINED = "!0";
	const MIXED_TYPE = "!1";
	const SCALAR_TYPE = "!2";
	const NULL_TYPE = "!3";
	const STRING_TYPE = "!4";
	const BOOL_TYPE = "!5";
	const INT_TYPE = "!6";
	const FLOAT_TYPE = "!7";

	const NULL_POSSIBLE = 1;
	const NULL_IMPOSSIBLE = 2;
	const NULL_UNKNOWN = 3;

	static public function nameToFromConst($str) {
		switch($str) {
			case static::UNDEFINED: return "undefined";
			case static::MIXED_TYPE: return "mixed";
			case static::SCALAR_TYPE: return "scalar";
			case static::STRING_TYPE: return "string";
			case static::BOOL_TYPE: return "bool";
			case static::INT_TYPE: return "int";
			case static::FLOAT_TYPE: return "float";
			case static::NULL_TYPE: return "null";
			return "$str";
		}
	}

	static public function constFromName($str) {
		if (strcasecmp($str,"null")==0) {
			return static::NULL_TYPE;
		} else if (strcasecmp($str,"bool")==0) {
			return static::BOOL_TYPE;
 		} else if (strcasecmp($str,"int")==0) {
			return static::INT_TYPE;
		} else if (strcasecmp($str,"float")==0) {
			return static::FLOAT_TYPE;
		} else if (strcasecmp($str,"string")==0) {
			return static::STRING_TYPE;
		} else {
			return $str;
		}
	}

	/**
	 * @var ScopeVar[]
	 */
	private $vars = [];

	/** @var  bool */
	private $isStatic;

	/** @var  bool */
	private $isGlobal;

	/** @var FunctionLike */
	private $inside;

	/** @var Scope */
	private $previous; // In an if/else scenario we want to compare the previous scope to see if both branches set a variable.


		/**
	 * Scope constructor.
	 *
	 * @param bool         $isStatic Set static
	 * @param bool         $isGlobal Set global
	 * @param FunctionLike $inside   Instance of FunctionLike (or null)
	 */
	function __construct($isStatic, $isGlobal = false, FunctionLike $inside = null, Scope $previous = null) {
		$this->isStatic = $isStatic;
		$this->isGlobal = $isGlobal;
		$this->inside = $inside;
		$this->previous = $previous;
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic() {
		return $this->isStatic;
	}

	/**
	 * isGlobal
	 *
	 * @return bool
	 */
	public function isGlobal() {
		return $this->isGlobal;
	}

	/**
	 * getInsideFunction
	 *
	 * @return FunctionLike
	 */
	public function getInsideFunction() {
		return $this->inside;
	}

	/**
	 * setVarType
	 *
	 * @param string $name The name
	 * @param string $type The type
	 *
	 * @return void
	 */
	public function setVarType($name, $type) {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$this->vars[$name] = $var;
		}

		$var = $this->vars[$name];
		if ($type == Scope::NULL_TYPE) {
			$var->canBeNull = Scope::NULL_POSSIBLE;
		}
		$var->type = $type;
		$var->modified = false;
		$var->modifiedLine = 0;
		$var->used = false;
	}

	/**
	 * setVarWritten
	 *
	 * @param string $name The name
	 * @param int    $line The line number
	 *
	 * @return void
	 */
	public function setVarWritten($name, $line) {
		if (isset($this->vars[$name])) {
			$this->vars[$name]->modified = true;
			$this->vars[$name]->modifiedLine = $line;
		}
	}

	/**
	 * setVarUsed
	 *
	 * @param string $name The name of the item
	 *
	 * @return void
	 */
	public function setVarUsed($name) {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$this->vars[$name] = $var;
		}

		$this->vars[$name]->used = true;
		$this->vars[$name]->modified = false;
		$this->vars[$name]->modifiedLine = 0;
	}

	public function setVarNull($name, $canBeNull = Scope::NULL_POSSIBLE) {
		if (!isset($this->vars[$name])) {
			$var= new ScopeVar();
			$var->name=$name;
			$var->type=Scope::UNDEFINED;
			$var->canBeNull = $canBeNull;
			$this->vars[$name]=$var;
		} else {
			$this->vars[$name]->canBeNull = $canBeNull;
		}
	}

	public function dump() {
		echo "Scope: \n";
		foreach($this->vars as $name=>$var) {
			echo "Name $name, Type ".$var->type. " ".($var->canBeNull == Scope::NULL_POSSIBLE ? "can be null" : "")."\n";
		}
	}

	/**
	 * markAllVarsUsed
	 *
	 * @return void
	 */
	public function markAllVarsUsed() {
		foreach ($this->vars as $var) {
			$var->used = true;
		}
	}

	/**
	 * When returning from certain scopes, we need to copy
	 * the list of used values.
	 * @param Scope $scope Instance of Scope
	 *
	 * @return void
	 */
	public function copyUsedVars(Scope $scope) {
		foreach ($scope->vars as $name=>$var) {
			if (!isset($this->vars[$name])) {
				$this->vars[$name] = $var;
			} else {
				if($this->getVarType($name)!=$var->type) {
					$this->vars[$name]->type=Scope::MIXED_TYPE;
				}
				if($var->canBeNull != Scope::NULL_IMPOSSIBLE) {
					$this->vars[$name]->canBeNull = $var->canBeNull;
				}
			}
		}
	}

	/**
	 * getUnusedVars
	 *
	 * @return array
	 */
	public function getUnusedVars() {
		$ret = [];
		foreach ($this->vars as $key=>$var) {
			if ($var->used) {
				$ret[$key] = $var->modifiedLine;
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
	function getVarType($name) {
		return isset($this->vars[$name]) ? $this->vars[$name]->type : self::UNDEFINED;
	}

	/**
	 * @param string $name The name of the var
	 * @return bool
	 */
	function getVarNullability($name) {
		return (isset($this->vars[$name]) ? $this->vars[$name]->canBeNull : false );
	}

	/**
	 * getScopeClone
	 *
	 * @return Scope
	 */
	public function getScopeClone(Scope $previous = null) {
		$newVars = [];
		foreach($this->vars as $var) {
			$newVar = clone $var;
			$newVars[$var->name]= $newVar;
		}
		$ret = new Scope($this->isStatic, $this->isGlobal, $this->inside, $previous);
		$ret->vars = $newVars;
		return $ret;
	}

	public function merge(Scope $other) {
		// See if any new vars were added to the scope or if existing ones were changed.
		foreach($other->vars as $name=>$otherVar) {
			if(!isset($this->vars[$name])) {
				$this->vars[$name]=$otherVar;
			} else {
				$this->vars[$name]->mergeVar($otherVar);
			}
		}
	}

	public function mergePrevious() {
		$this->merge($this->previous);
	}
}