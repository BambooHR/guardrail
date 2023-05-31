<?php namespace BambooHR\Guardrail\SymbolTable;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\FunctionAbstraction as AbstractionFunction;
use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractionClass;
use BambooHR\Guardrail\Abstractions\ReflectedClass;
use BambooHR\Guardrail\Abstractions\ReflectedClassMethod;
use BambooHR\Guardrail\Abstractions\ReflectedFunction;
use BambooHR\Guardrail\ObjectCache;
use BambooHR\Guardrail\NodeVisitors\Grabber;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Class SymbolTable
 *
 * @package BambooHR\Guardrail\SymbolTable
 */
abstract class SymbolTable {
	const TYPE_CLASS = 1;
	const TYPE_FUNCTION = 2;
	const TYPE_INTERFACE = 3;
	const TYPE_TRAIT = 4;
	const TYPE_DEFINE = 5;

	/**
	 * @var ObjectCache
	 */
	protected $cache;

	/**
	 * @var string
	 */
	protected $basePath;

	/**
	 * SymbolTable constructor.
	 *
	 * @param string $basePath The base path
	 */
	public function __construct($basePath) {
		$this->cache = new ObjectCache();
		$this->basePath = $basePath;
	}

	/**
	 * getClass
	 *
	 * @param string $name The name
	 *
	 * @return \BambooHR\Guardrail\NodeVisitors\Interface_|\BambooHR\Guardrail\NodeVisitors\Trait_|null|Class_|string
	 */
	public function getClass($name) {
		$cacheName = strtolower($name);
		$file = $this->getClassFile($name);
		if (!$file) {
			return null;
		}
		$ob = $this->cache->get("Class:" . $cacheName);
		if (!$ob) {
			$ob = Grabber::getClassFromFile($this, $file, $name, Class_::class);
			if ($ob) {
				$this->cache->add("Class:" . $cacheName, $ob);
			}
		}
		return $ob;
	}

	/**
	 * isParentClassOrInterface
	 *
	 * Checks all parent classes and parent interfaces to see if $child is can be used in their place.
	 *
	 * @param string $potentialParent The potential parent
	 * @param string $child           The child
	 *
	 * @return bool
	 */
	public function isParentClassOrInterface($potentialParent, $child) {
		while ($child) {
			if (strcasecmp($potentialParent, $child) == 0) {
				return true;
			}
			$child = $this->getAbstractedClass($child);
			if (!$child) {
				return false;
			}
			foreach ($child->getInterfaceNames() as $interface) {
				if ($this->isParentClassOrInterface($potentialParent, $interface)) {
					return true;
				}
			}
			$child = $child->getParentClassName();
			if ($child=="" && strcasecmp($potentialParent, "object") == 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * isDefinedClass
	 *
	 * More efficient than getAbstractedClass, for the cases where you don't need the class.
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	abstract public function isDefinedClass($name);

	/**
	 * An opportunity for the symbol table to update it's definition
	 * of a class after traits have been imported.
	 * @param ClassLike $class Instance of ClassLike
	 * @return mixed
	 */
	abstract public function updateClass(ClassLike $class);

	/** @return void */
	public function begin() {
	}

	/** @return void */
	public function commit() {

	}

	/**
	 * getAbstractedClass
	 *
	 * @param string $name The name
	 *
	 * @return AbstractionClass
	 */
	public function getAbstractedClass($name) {
		$name = strval($name);
		$cacheName = strtolower($name);
		$ob = $this->cache->get("AClass:" . $cacheName);
		if (!$ob) {
			$tmp = $this->getClassOrInterface($name);
			if ($tmp) {
				$ob = new AbstractionClass($tmp);
			} else if (strpos($name, "\\") === false) {
				try {
					$refl = new ReflectionClass($name);
					$ob = new ReflectedClass($refl);
				} catch (ReflectionException $exception) {
					$ob = null;
				}
			}
			if ($ob) {
				$this->cache->add("AClass:" . $cacheName, $ob);
			}
		}
		return $ob;
	}

	/**
	 * getAbstractedMethod
	 *
	 * @param string $className  The class name
	 * @param string $methodName The method name
	 *
	 * @return AbstractionClass|\BambooHR\Guardrail\Abstractions\ClassMethod|ReflectedClassMethod|null|string
	 */
	public function getAbstractedMethod($className, $methodName) {
		$cacheName = strtolower($className . "::" . $methodName);
		$ob = $this->cache->get("AClassMethod:" . $cacheName);
		if (!$ob) {
			$ob = Util::findAbstractedMethod($className, $methodName, $this);
			if (!$ob && strpos($className, "\\") === false) {
				try {
					$refl = new ReflectionMethod($className, $methodName);
					$ob = new ReflectedClassMethod($refl);
				} catch (ReflectionException $exception) {
					$ob = null;
				}
			}
			if ($ob) {
				$this->cache->add("AClassMethod:" . $cacheName, $ob);
			}
		}
		return $ob;
	}

	/**
	 * getAbstractedFunction
	 *
	 * @param string $name The name
	 *
	 * @return AbstractionFunction|ReflectedFunction|null
	 */
	public function getAbstractedFunction($name) {
		$func = $this->getFunction($name);
		if ($func) {
			$ob = new AbstractionFunction($func);
		} else {
			try {
				$refl = new ReflectionFunction($name);
				$ob = new ReflectedFunction($refl);
			} catch (ReflectionException $exception) {
				$ob = null;
			}
		}
		return $ob;
	}

	/**
	 * getTrait
	 *
	 * @param string $name The name
	 *
	 * @return \BambooHR\Guardrail\NodeVisitors\Interface_|\BambooHR\Guardrail\NodeVisitors\Trait_|null|Class_|string
	 */
	public function getTrait($name) {
		$file = $this->getTraitFile($name);
		if (!$file) {
			return null;
		}
		$ob = $this->cache->get("Trait:" . $name);
		if (!$ob) {
			$ob = Grabber::getClassFromFile( $this, $file, $name, Trait_::class);
			if ($ob) {
				$this->cache->add("Trait:" . $name, $ob);
			}
		}
		return $ob;
	}

	public function getAbstractedTrait($name) {
		$cacheKey = 'ATrait:' . strtolower(strval($name));
		$ob = $this->cache->get($cacheKey);
		if ($ob !== null) {
			return $ob;
		}
		$trait = $this->getTrait($name);
		if ($trait === null) {
			return null;
		}
		$ob = new AbstractionClass($trait);
		$this->cache->add($cacheKey, $ob);
		return $ob;
	}

	/**
	 * isDefined
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function isDefined($name) {
		$file = $this->getDefineFile($name);
		return boolval($file);
	}

	/**
	 * removeFileFromIndex
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	abstract function removeFileFromIndex($name);

	/**
	 * adjustBasePath
	 *
	 * Converts phar:// psuedo-paths to relative paths.
	 * Converts relative paths to paths relative to $this->basePath
	 * Leaves absolute paths unchanged
	 *
	 * @param string $fileName The filename
	 *
	 * @return string
	 */
	public function adjustBasePath($fileName) {
		if (strpos($fileName, "phar://") === 0) {
			$fileName = substr($fileName, 7);
		} else if (!empty($fileName) && strpos($fileName, "/") !== 0) {
			$fileName = $this->basePath . "/" . $fileName;
		}
		return $fileName;
	}

	/**
	 * @param string $fileName A potentially absolute path
	 * @return string
	 */
	public function removeBasePath($fileName) {
		if (strpos($fileName, $this->basePath) === 0) {
			return substr($fileName, strlen($this->basePath));
		} else {
			return $fileName;
		}
	}

	/**
	 * getInterface
	 *
	 * @param string $name The name
	 *
	 * @return \BambooHR\Guardrail\NodeVisitors\Interface_|\BambooHR\Guardrail\NodeVisitors\Trait_|null|Class_|string
	 */
	public function getInterface($name) {
		$file = $this->getInterfaceFile($name);
		if (!$file) {
			return null;
		}
		$ob = $this->cache->get("Interface:" . $name);
		if (!$ob) {
			$ob = Grabber::getClassFromFile($this, $file, $name, Interface_::class);
			if ($ob) {
				$this->cache->add("Interface:" . $name, $ob);
			}
		}
		return $ob;
	}

	/**
	 * getFunction
	 *
	 * @param string $name The name
	 *
	 * @return \BambooHR\Guardrail\NodeVisitors\Interface_|\BambooHR\Guardrail\NodeVisitors\Trait_|null|Class_|string
	 */
	public function getFunction($name) {
		$file = $this->getFunctionFile($name);
		if (!$file) {
			return null;
		}
		$ob = $this->cache->get("Function:" . $name);
		if (!$ob) {
			$ob = Grabber::getClassFromFile($this, $file, $name, Function_::class);
			if ($ob) {
				$this->cache->add("Function:" . $name, $ob);
			}
		}
		return $ob;
	}

	/**
	 * getClassOrInterface
	 *
	 * @param string $name The name
	 *
	 * @return \BambooHR\Guardrail\NodeVisitors\Interface_|\BambooHR\Guardrail\NodeVisitors\Trait_|null|Class_|string
	 */
	public function getClassOrInterface($name) {
		return $this->getClass($name) ?: $this->getInterface($name);
	}

	/**
	 * ignoreType
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function ignoreType($name) {
		$name = strtolower($name);
		return ($name == 'exception' || $name == 'stdclass' || $name == 'iterator' || $name == 'object' || $name=='mixed' || $name=='null');
	}

	/**
	 * classExistsInNamespace
	 *
	 * @param array  $classes The array of classes
	 * @param string $name    The name of the class we are checking
	 *
	 * @return bool
	 */
	protected function classExistsInNamespace($classes, $name) {
		foreach ($classes as $class => $file) {
			if (preg_match('/' . preg_quote(strtolower($name), '/') . '$/', $class)) {
				return true;
			}
		}
	}

	/**
	 * addClass
	 *
	 * @param string $name  The name
	 * @param Class_ $class Instance of ClassAbstraction
	 * @param string $file  The file
	 *
	 * @return mixed
	 */
	abstract function addClass($name, Class_ $class, $file);

	/**
	 * addInterface
	 *
	 * @param string     $name      The name
	 * @param Interface_ $interface Instance of Interface_
	 * @param string     $file      The file
	 *
	 * @return mixed
	 */
	abstract function addInterface($name, Interface_ $interface, $file);

	/**
	 * getClassFile
	 *
	 * @param string $className The class name
	 *
	 * @return string
	 */
	abstract function getClassFile($className);

	/**
	 * getTraitFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	abstract function getTraitFile($name);

	/**
	 * addTrait
	 *
	 * @param string $name  The name
	 * @param Trait_ $trait Instance of Trait_
	 * @param string $file  The file
	 *
	 * @return mixed
	 */
	abstract function addTrait($name, Trait_ $trait, $file);

	/**
	 * getInterfaceFile
	 *
	 * @param string $interfaceName The name of the interface
	 *
	 * @return string
	 */
	abstract function getInterfaceFile($interfaceName);

	/**
	 * getFunctionFile
	 *
	 * @param string $methodName The method name
	 *
	 * @return string
	 */
	abstract function getFunctionFile($methodName);

	/**
	 * getDefineFile
	 *
	 * @param string $defineName The defineName
	 *
	 * @return mixed
	 */
	abstract function getDefineFile($defineName);

	/**
	 * addDefine
	 *
	 * @param string $name   The name
	 * @param Node   $define Instance of Node
	 * @param string $file   The file
	 *
	 * @return mixed
	 */
	abstract function addDefine($name, Node $define, $file);


	/**
	 * getClassesThatUseAnyTrait
	 *
	 * @return array
	 */
	abstract public function getClassesThatUseAnyTrait();

	/**
	 * classExistsAnyNamespace
	 *
	 * @param string $name The class name
	 *
	 * @return bool
	 */
	abstract public function classExistsAnyNamespace($name);
}