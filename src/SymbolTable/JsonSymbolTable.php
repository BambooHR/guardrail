<?php namespace BambooHR\Guardrail\SymbolTable;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\FunctionAbstraction as AbstractFunction;
use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractClass;
use BambooHR\Guardrail\Abstractions\ReflectedClass;
use BambooHR\Guardrail\Abstractions\ReflectedFunction;
use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use Exception;
use PDO;
use PDOException;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use ReflectionClass;
use ReflectionException;

/**
 * Class SqliteSymbolTable
 */
class JsonSymbolTable extends SymbolTable implements PersistantSymbolTable {

	/** @var array [$type][$name] = ['has_trait'=>,'data'=>,'file'=>] */
	private $index = [
		SymbolTable::TYPE_CLASS => [],
		SymbolTable::TYPE_FUNCTION => [],
		SymbolTable::TYPE_INTERFACE => [],
		SymbolTable::TYPE_TRAIT => [],
		SymbolTable::TYPE_DEFINE => []
	];

	private $processNumber = 0;

	private $fileName;


	/**
	 * SqliteSymbolTable constructor.
	 *
	 * @param string $fileName The file name
	 * @param string $basePath The base path
	 */
	public function __construct($fileName, $basePath) {
		parent::__construct($basePath);
		$this->fileName = $fileName;
	}

	/**
	 * Disconnect, needed if we're going to pcntl_fork()
	 *
	 * @return void
	 */
	public function disconnect() {
		$fileName = $this->fileName . ($this->processNumber ? '.' . $this->processNumber : '');
		$str=json_encode($this->index,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
		file_put_contents($fileName, $str) ;
	}

	/**
	 * Reconnect after a pcntl_fork()
	 * @param int $processNumber The number representing which indexer is running.
	 * @return void
	 */
	public function connect($processNumber) {
		$this->processNumber = $processNumber;
		$fileName = $this->fileName . ($this->processNumber ? '.' . $this->processNumber : '');
		if (file_exists($fileName)) {
			$this->index = json_decode(file_get_contents($fileName), true);
		} else {
			$this->index = [];
		}
	}


	/**
	 * addType
	 *
	 * @param string $name     The name
	 * @param string $file     The file
	 * @param string $type     The type
	 * @param int    $hasTrait Has a trait
	 * @param string $data     The data
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addType($name, $file, $type, $hasTrait = 0, $data = "") {
		if (!isset($this->index[$type])) {
			$this->index[$type] = [];
		}
		$this->index[$type][strtolower($name)] = ['file' => $file, 'has_trait' => $hasTrait, 'data' => $data];
	}

	/**
	 * We save up batches of inserts and then insert them all at once in a transaction.
	 *
	 * @return void
	 */
	function flushInserts() {
	}

	/**
	 * Add the index to the symbol table.  This is faster than adding it ahead of time.
	 *
	 * @param int $processCount The total number of indexing processes.
	 * @return void
	 */
	function indexTable($processCount) {
		for ($index = 1; $index <= $processCount; ++$index) {
			$fileName = $this->fileName . '.' . $index;
			$content = file_get_contents($fileName);
			$arr = json_decode($content, true);
			foreach ($arr as $type => $arr2) {
				if (!isset($this->index[$type])) {
					$this->index[$type] = [];
				}
				foreach ($arr2 as $name => $entry) {
					$this->index[$type][$name] = $entry;
				}
			}
			unlink($fileName);
		}
		$this->disconnect();
	}

	/**
	 * getType
	 *
	 * @param string $name The name
	 * @param string $type The type
	 *
	 * @return string
	 */
	public function getType($name, $type) {
		$name = strtolower($name);

		if (!isset($this->index[$type]) || !isset($this->index[$type][$name])) {
			return "";
		}

		$result = $this->index[$type][$name]['file'];
		return $this->adjustBasePath($result);
	}

	/**
	 * getClassOrInterfaceData
	 *
	 * @param string $name The name
	 *
	 * @return mixed|string
	 */
	public function getClassOrInterfaceData($name) {
		return $this->getData($name);

	}

	/**
	 * getData
	 *
	 * @param string $name The name
	 * @param int    $type The type
	 *
	 * @return mixed|string
	 */
	public function getData($name, $type = self::TYPE_CLASS) {

		$name = strtolower($name);
		if ($type == self::TYPE_FUNCTION) {
			if (!isset($this->index[$type]) || !isset($this->index[$type][$name])) {
				return "";
			}
			$result = $this->index[$type][$name]['data'];
		} else if ($type == self::TYPE_CLASS) {
			if (isset($this->index[self::TYPE_CLASS]) && isset($this->index[self::TYPE_CLASS][$name])) {
				$result = $this->index[self::TYPE_CLASS][$name]['data'];
			} else if (isset($this->index[self::TYPE_INTERFACE]) && isset($this->index[self::TYPE_INTERFACE][$name])) {
				$result = $this->index[self::TYPE_INTERFACE][$name]['data'];
			} else {
				return "";
			}
		}
		return self::unserializeObject($result);
	}

	/**
	 * @return void
	 */
	public function begin() {

	}

	/**
	 * @return void
	 */
	public function commit() {

	}

	/**
	 * getInterface
	 *
	 * @param string $name The name
	 *
	 * @return \BambooHR\Guardrail\NodeVisitors\Interface_|\BambooHR\Guardrail\NodeVisitors\Trait_|null|Class_|string
	 */
	public function getInterface($name) {
		return $this->getClassOrInterface($name);
	}

	/**
	 * getAbstractedFunction
	 *
	 * @param string $name The name
	 *
	 * @return AbstractFunction|ReflectedFunction|mixed|null|string
	 */
	public function getAbstractedFunction($name) {
		$ob = $this->cache->get("AFunction:" . $name);
		if (!$ob) {
			$ob = $this->getData($name, self::TYPE_FUNCTION);
			if ($ob) {
				$ob = new AbstractFunction($ob);
			} else {
				try {
					$refl = new \ReflectionFunction($name);
					$ob = new ReflectedFunction($refl);
				} catch (ReflectionException $exception) {
					$ob = null;
				}
			}
		}
		if ($ob) {
			$this->cache->add("AFunction:" . $name, $ob);
		}
		return $ob;
	}

	/**
	 * getClassesThatUseAnyTrait
	 *
	 * @return array
	 */
	public function getClassesThatUseAnyTrait() {
		$ret = [];
		foreach ($this->index[self::TYPE_CLASS] as $name => $entry) {
			if ($entry['has_trait']) {
				$ret[] = $name;
			}
		}
		return $ret;
	}

	/**
	 * updateClass
	 *
	 * @param ClassLike $class Instance of ClassLike
	 *
	 * @return void
	 */
	public function updateClass(ClassLike $class) {
		$name = strtolower($class->namespacedName);

		$clone = $this->stripMethodContents($class);
		$serializedString = self::serializeObject($clone);
		$type = $class instanceof Trait_ ? self::TYPE_TRAIT : self::TYPE_CLASS;

		$this->index[$type][$name]['data'] = $serializedString;
	}

	/**
	 * removeFileFromIndex
	 *
	 * @param string $name The name
	 *
	 * @return void
	 */
	public function removeFileFromIndex($name) {
		foreach ($this->index as $type => $arr) {
			foreach ($arr as $elName => $data) {
				if ($data['file'] == $name) {
					unset($this->inset[$type][$elName]);
				}
			}
		}
	}

	/**
	 * stripMethodContents
	 *
	 * @param ClassLike $class Instance of ClassLike
	 *
	 * @return mixed
	 */
	public function stripMethodContents(ClassLike $class) {
		// Make a deep copy and then remove implementation code (to save space).
		$clone = unserialize(serialize($class));
		foreach ($clone->stmts as $index => &$stmt) {
			if ($stmt instanceof ClassMethod) {
				// Note: the attribute could already be set, so we explicitly check for === null rather than ==
				if ($stmt->getAttribute('variadic_implementation', null) === null) {
					$stmt->setAttribute("variadic_implementation", VariadicCheckVisitor::isVariadic($stmt->stmts));
				}
				$stmt->stmts = [];
			}
		}
		return $clone;
	}

	/**
	 * serializeObject
	 *
	 * PHP's serialize() is very fast, but it produces a bloated serialization string.  We deflate it to make it 10x smaller
	 * Then we base64_encode to make it a little bit safer to deal with in the db layer.
	 *
	 * @param string $string The string
	 *
	 * @return string
	 */
	private static function serializeObject($string) {
		//return base64_encode( serialize($string) );
		//return ( gzdeflate( serialize( $string ) ) );
		return base64_encode(gzdeflate(serialize($string)));
		//return serialize( $string );
	}


	/**
	 * unserializeObject
	 *
	 * @param string $string The string
	 *
	 * @return mixed
	 */
	private static function unserializeObject($string) {
		//return unserialize(base64_decode($string));
		//return unserialize( gzinflate( ( $string ) ) );
		return unserialize(gzinflate(base64_decode($string)));
		//return unserialize( $string );
	}

	/**
	 * addClass
	 *
	 * @param string $name  The name
	 * @param Class_ $class Instance of Class
	 * @param string $file  The file
	 *
	 * @return void
	 */
	public function addClass($name, ClassLike $class, $file) {
		$usesTrait = 0;
		foreach ($class->stmts as $stmt) {
			if ($stmt instanceof TraitUse) {
				$usesTrait = 1;
			}
		}
		$clone = $this->stripMethodContents($class);
		$this->addType($name, $file, self::TYPE_CLASS, $usesTrait, self::serializeObject($clone));
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
	public function isDefinedClass($name) {
		$cacheName = strtolower($name);
		if (
			$this->cache->get("AClass:" . $cacheName) ||
			$this->getType($name, self::TYPE_CLASS) ||
			$this->getType($name, self::TYPE_INTERFACE)
		) {
			return true;
		}
		try {
			new ReflectionClass($name);
			return true;
		} catch (ReflectionException $exception) {
			return false;
		}
	}

	/**
	 * getAbstractedClass
	 *
	 * @param string $name The name
	 *
	 * @return AbstractClass
	 */
	public function getAbstractedClass($name) {
		$name = strval($name);
		$cacheName = strtolower($name);
		$ob = $this->cache->get("AClass:" . $cacheName);
		if (!$ob) {
			$tmp = $this->getClassOrInterfaceData($name);
			if ($tmp) {
				$ob = new AbstractClass($tmp);
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
	 * addInterface
	 *
	 * @param string     $name      The name
	 * @param Interface_ $interface Instance of Interface_
	 * @param string     $file      The file
	 *
	 * @return void
	 */
	public function addInterface($name, Interface_ $interface, $file) {
		$this->addType($name, $file, self::TYPE_INTERFACE, 0, self::serializeObject($interface));
	}

	/**
	 * addFunction
	 *
	 * @param string    $name     The name
	 * @param Function_ $function Instance of FunctionAbstraction
	 * @param string    $file     The file
	 *
	 * @return void
	 */
	public function addFunction($name, Function_ $function, $file) {
		$clone = clone $function;
		$clone->setAttribute("variadic_implementation", VariadicCheckVisitor::isVariadic($function->stmts));
		$clone->stmts = [];
		$this->addType($name, $file, self::TYPE_FUNCTION, 0, self::serializeObject($clone));
	}

	/**
	 * addTrait
	 *
	 * @param string $name  The name
	 * @param Trait_ $trait Instance of Trait_
	 * @param string $file  The file
	 *
	 * @return void
	 */
	public function addTrait($name, Trait_ $trait, $file) {
		$this->addType($name, $file, self::TYPE_TRAIT);
	}

	/**
	 * addDefine
	 *
	 * @param string $name   The name
	 * @param Node   $define Instance of Node
	 * @param string $file   The file
	 *
	 * @return void
	 */
	public function addDefine($name, Node $define, $file) {
		$this->addType($name, $file, self::TYPE_DEFINE);
	}

	/**
	 * getDefineFile
	 *
	 * @param string $name The name
	 *
	 * @return string
	 */
	public function getDefineFile($name) {
		return $this->getType($name, self::TYPE_DEFINE);
	}

	/**
	 * getTraitFile
	 *
	 * @param string $name The name
	 *
	 * @return string
	 */
	public function getTraitFile($name) {
		return $this->getType($name, self::TYPE_TRAIT);
	}

	/**
	 * getClassFile
	 *
	 * @param string $className The class name
	 *
	 * @return string
	 */
	public function getClassFile($className) {
		return $this->getType($className, self::TYPE_CLASS);
	}

	/**
	 * getInterfaceFile
	 *
	 * @param string $interfaceName The interface name
	 *
	 * @return string
	 */
	public function getInterfaceFile($interfaceName) {
		return $this->getType($interfaceName, self::TYPE_INTERFACE);
	}

	/**
	 * getFunctionFile
	 *
	 * @param string $functionName The name of the function
	 *
	 * @return string
	 */
	public function getFunctionFile($functionName) {
		return $this->getType($functionName, self::TYPE_FUNCTION);
	}

	/**
	 * classExistsAnyNamespace
	 *
	 * @param string $name The class name
	 *
	 * @return bool
	 */
	public function classExistsAnyNamespace($name) {
		$name = strtolower($name);
		foreach ([self::TYPE_INTERFACE, self::TYPE_CLASS] as $type) {
			foreach ($this->index[$type] as $elName => $data) {
				if (strrpos($name, $elName) === strlen($elName) - strlen($name)) {
					return true;
				}
			}
		}
		return false;
	}
}