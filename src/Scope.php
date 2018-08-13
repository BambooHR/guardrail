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
	const ARRAY_TYPE = "!8";

	const NULL_POSSIBLE = 1;
	const NULL_IMPOSSIBLE = 2;
	const NULL_UNKNOWN = 3;

	/**
	 * @param string $str A string representing an internal type
	 * @return string
	 */
	static public function nameFromConst($str) {
		switch ($str) {
			case static::UNDEFINED:
				return "undefined";
			case static::MIXED_TYPE:
				return "mixed";
			case static::SCALAR_TYPE:
				return "scalar";
			case static::STRING_TYPE:
				return "string";
			case static::BOOL_TYPE:
				return "bool";
			case static::INT_TYPE:
				return "int";
			case static::FLOAT_TYPE:
				return "float";
			case static::NULL_TYPE:
				return "null";
			default:
				return $str;
		}
	}

	/**
	 * @param string $str A string representing a user supplied type.
	 * @return string
	 */
	static public function constFromName($str) {
		if (strcasecmp($str, "null") == 0) {
			return static::NULL_TYPE;
		} elseif (strcasecmp($str, "bool") == 0) {
			return static::BOOL_TYPE;
		} elseif (strcasecmp($str, "int") == 0) {
			return static::INT_TYPE;
		} elseif (strcasecmp($str, "float") == 0) {
			return static::FLOAT_TYPE;
		} elseif (strcasecmp($str, "string") == 0) {
			return static::STRING_TYPE;
		} elseif (strcasecmp($str, "mixed") == 0) {
			return static::MIXED_TYPE;
		} elseif (strcasecmp($str, "array") == 0) {
			return static::ARRAY_TYPE;
		} else {
			return $str;
		}
	}

	/**
	 * @param string $str             The docblock type
	 * @param string $insideClassName The string to use for $this or self::
	 * @param string $staticClassName The string to use for static::
	 * @return string
	 */
	static public function constFromDocBlock($str, $insideClassName="", $staticClassName="") {
		if (strcasecmp($str, "object") == 0) {
			return static::MIXED_TYPE;
		} elseif (strcasecmp($str, "integer") == 0) {
			return static::INT_TYPE;
		} elseif (strcasecmp($str, "boolean") == 0) {
			return static::BOOL_TYPE;
		} elseif (strcasecmp($str, "\$this") == 0) {
			return $insideClassName;
		} elseif (strcasecmp($str, "static") == 0) {
			return $staticClassName;
		} elseif (strcasecmp($str, "self") == 0) {
			return $insideClassName;
		} else {
			return self::constFromName($str);
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
	 * @param Scope        $previous The previous scope (used for control blocks)
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
	 * @param int    $line Line number
	 *
	 * @return void
	 */
	public function setVarType($name, $type, $line) {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$this->vars[$name] = $var;
		}

		$var = $this->vars[$name];
		if ($type == self::NULL_TYPE) {
			$var->attributes |= Attributes::NULL_POSSIBLE;
		}
		$var->type = $type;
		$this->setVarWritten($name, $line);
	}

	public function setVarAttributes($name, $attributes) {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$var->attributes = $attributes;
			$this->vars[$name]= $var;
		} else {
			$this->vars[$name]->attributes = $attributes;
		}
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

	/**
	 * @param string $name      The name of the variable
	 * @param int    $canBeNull It's nullability state
	 * @return void
	 */
	public function setVarNull($name, $canBeNull ) {
		if (!isset($this->vars[$name])) {
			$var = new ScopeVar();
			$var->name = $name;
			$var->type = self::UNDEFINED;
			$var->attributes = ($canBeNull ? Attributes::NULL_POSSIBLE : 0);
			$this->vars[$name] = $var;
		} else {
			$ob = $this->vars[$name];
			$ob->attributes = ($ob->attributes & ~Attributes::NULL_POSSIBLE) | ($canBeNull ? Attributes::NULL_POSSIBLE : 0);
		}
	}

	/**
	 * @return void
	 */
	public function dump() {
		echo "\nScope: \n";
		foreach ($this->vars as $name => $var) {
			echo "  Name $name, Type " . $var->type . " " . ($var->attributes & Attributes::NULL_POSSIBLE ? "can be null" : "") . " " . ($var->used ? "used" : " not used") . " ".$var->attributes."\n";

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
		foreach ($scope->vars as $name => $var) {
			if (!isset($this->vars[$name])) {
				$this->vars[$name] = $var;
			} else {
				if ($this->getVarType($name) != $var->type) {
					$this->vars[$name]->type = self::MIXED_TYPE;
				}
				if ($var->used) {
					$this->setVarUsed($var->name);
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
		foreach ($this->vars as $key => $var) {
			if (!$var->used) {
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
		return (isset($this->vars[$name]) ? $this->vars[$name]->attributes & Attributes::NULL_POSSIBLE : false );
	}


	function getVarAttributes($name) {
		return (isset($this->vars[$name]) ? $this->vars[$name]->attributes : 0);
	}

	/**
	 * getScopeClone
	 * @param Scope $previous The previous scope
	 * @return Scope
	 */
	public function getScopeClone(Scope $previous = null) {
		$newVars = [];
		foreach ($this->vars as $var) {
			$newVar = clone $var;
			$newVars[$var->name] = $newVar;
		}
		$ret = new Scope($this->isStatic, $this->isGlobal, $this->inside, $previous);
		$ret->vars = $newVars;
		return $ret;
	}

	/**
	 * @param Scope $other -
	 * @return void
	 */
	public function merge(Scope $other) {
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
	 * @return void
	 */
	public function mergePrevious() {
		$this->merge($this->previous);
	}
}