<?php

namespace BambooHR\Guardrail\SymbolTable;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\FunctionAbstraction as AbstractFunction;
use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractClass;
use BambooHR\Guardrail\Abstractions\ReflectedClass;
use BambooHR\Guardrail\Abstractions\ReflectedFunction;
use BambooHR\Guardrail\Evaluators\Expression\Scalar;
use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\TypeParser;
use Exception;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
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

	private const TYPE_STRING_TABLE = 6;

	private $processNumber = 0;

	private $fileName;

	private TypeStringTable $types;

	/**
	 * SqliteSymbolTable constructor.
	 *
	 * @param string $fileName The file name
	 * @param string $basePath The base path
	 */
	public function __construct($fileName, $basePath) {
		parent::__construct($basePath);
		$this->types = new TypeStringTable();
		$this->fileName = $fileName;
		$this->parser = new TypeParser( fn($typeString)=>new Node\Name\FullyQualified($typeString));
	}

	/**
	 * Disconnect, needed if we're going to pcntl_fork()
	 *
	 * @return void
	 */
	public function disconnect() {
		$fileName = $this->fileName . ($this->processNumber ? '.' . $this->processNumber : '');
		$this->index[self::TYPE_STRING_TABLE] = $this->types;
		$str = json_encode($this->index, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
		file_put_contents($fileName, $str) ;
	}

	public function delete() {
		$fileName = $this->fileName . ($this->processNumber ? '.' . $this->processNumber : '');
		if (file_exists($fileName)) {
			unlink($fileName);
		}
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
			$this->types = TypeStringTable::fromArray($this->index[self::TYPE_STRING_TABLE]);
			unset($this->index[self::TYPE_STRING_TABLE]);
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
		$name = strtolower($name);
		if (!array_key_exists($type, $this->index)) {
			$this->index[$type] = [];
		}
		if (!array_key_exists($name, $this->index[$type])) {
			$this->index[$type][$name] = ['file' => $file ];
			if (!empty($data)) {
				$this->index[$type][$name]['data'] = $data;
			}
			if ($hasTrait) {
				$this->index[$type][$name]['has_trait'] = $hasTrait;
			}
		}
	}

	/**
	 * We save up batches of inserts and then insert them all at once in a transaction.
	 *
	 * @return void
	 */
	function flushInserts() {
	}

	/**
	 *
	 * Unserialize each instance, merge by insert into the combined table.  This way, all the string
	 * tables also get merged and renumbered.
	 *
	 * @param int $processCount The total number of indexing processes.
	 * @return void
	 */
	function indexTable($processCount) {
		for ($index = 1; $index <= $processCount; ++$index) {
			$fileName = $this->fileName;
			$table = new JsonSymbolTable($fileName, $this->basePath);
			$table->connect($index);
			foreach ($table->index as $type => $arr2) {
				foreach ($arr2 as $name => $entryString) {
					switch ($type) {
						case self::TYPE_TRAIT:
							$this->addType($name, $entryString['file'], $type, isset($entryString['has_trait']));
						break;
						case self::TYPE_CLASS:
						case self::TYPE_INTERFACE:
							$this->addType($name, $entryString['file'], $type, isset($entryString['has_trait']), $this->serializeClass( $table->unserializeClass($entryString['data'])));
							break;
						case self::TYPE_FUNCTION:
							$this->addType($name, $entryString['file'], $type, 0, $this->serializeFunction($table->unserializeFunction($entryString['data'])));
							break;
						case self::TYPE_DEFINE:
							$this->addType($name, $entryString['file'], $type);
							break;
					}
				}
			}
			$table->delete();
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
			return $this->unserializeFunction($result);
		} elseif ($type == self::TYPE_CLASS) {
			if (isset($this->index[self::TYPE_CLASS]) && isset($this->index[self::TYPE_CLASS][$name])) {
				$result = $this->index[self::TYPE_CLASS][$name]['data'];
			} elseif (isset($this->index[self::TYPE_INTERFACE]) && isset($this->index[self::TYPE_INTERFACE][$name])) {
				$result = $this->index[self::TYPE_INTERFACE][$name]['data'];
			} else {
				return "";
			}
		}
		return $this->unserializeClass($result);
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
			if (isset($entry['has_trait'])) {
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

		$serializedString = $this->serializeClass($class);
		$type = $class instanceof Trait_ ? self::TYPE_TRAIT : self::TYPE_CLASS;

		$this->index[$type][$name]['data'] = $serializedString;
	}

	/**
	 * stripMethodContents
	 *
	 * @param ClassLike $class Instance of ClassLike
	 *
	 * @return mixed
	 */
	public static function stripMethodContents(ClassLike $class) {
		// Make a deep copy and then remove implementation code (to save space).
		$clone = clone $class;
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
		$this->addType($name, $file, self::TYPE_CLASS, $usesTrait, $this->serializeClass($class));
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
			} elseif (strpos($name, "\\") === false) {
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
		$this->addType($name, $file, self::TYPE_INTERFACE, 0, $this->serializeClass($interface));
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
		$this->addType($name, $file, self::TYPE_FUNCTION, 0, $this->serializeFunction($function));
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

	function serializeFunction(Function_ $function): string {
		$ret = "F" . $function->namespacedName . ($function->returnsByRef() ? "&" : "");
		$ret .= $this->serializeParams($function->params);
		if ($function->returnType !== null || $function->getAttribute("namespacedReturn") !== null) {
			$ret .= ":";
			if ($function->returnType !== null) {
				$ret .= ($this->types->add($function->returnType));
			}
			if ($function->getAttribute("namespacedReturn") !== null) {
				$ret .= "@" . ($this->types->add($function->getAttribute("namespacedReturn")));
			}
		}
		if (count($function->getAttribute("throws", [])) > 0) {
			$ret .= "T" . implode(",", array_map(fn($type)=>$this->types->add($type), $function->getAttribute("throws")));
		}
		if ($function->getAttribute("variadic_implementation")) {
			$ret .= "V";
		}
		$ret .= ";";
		return $ret;
	}


	function serializeProperty(Property $prop): string {
		$ret = "";
		$flags = $prop->flags;
		$type = $prop->type;
		foreach ($prop->props as $propProp) {
			$ret .= "P" . ($type ? $this->types->add($type) : "");
			if ($prop->getAttribute("namespacedType")) {
				$ret .= "@" . $this->types->add($prop->getAttribute("namespacedType"));
			}
			$ret .= '$' . $propProp->name . ($flags !== 0 ? " " . ($flags) : "") . ";";
		}
		return $ret;
	}

	function serializeMethod(ClassMethod $method): string {
		$ret = "M" . $method->name .
			($method->returnsByRef() ? "&" : " ") .
			($method->flags !== 0 ? strval($method->flags) : "") .
			$this->serializeParams($method->params);
		if ($method->returnType !== null || $method->getAttribute('DocBlockReturnType') !== null) {
			$ret .= ":" ;
			if ($method->returnType !== null) {
				$ret .= $this->types->add($method->returnType);
			}
			if ($method->getAttribute('namespacedReturn')) {
				$ret .= "@" . $this->types->add($method->getAttribute('namespacedReturn'));
			}
		}
		if (count($method->getAttribute('throws', [])) > 0) {
			$ret .= "T" . implode(",", array_map(fn($type)=>$this->types->add($type), $method->getAttribute('throws')));
		}
		if ($method->getAttribute('variadic_implementation')) {
			$ret .= "V";
		}
		$ret .= ";";
		return $ret;
	}

	function serializeConst(ClassConst $classConst): string {
		$flags = $classConst->flags;
		$ret = "";
		foreach ($classConst->consts as $const) {
			$ret .= "C" . $const->name .
				($classConst->type ? " " . $this->types->add($classConst->type) : "") .
				($flags != 0 ? " " . $flags : "") .
				";";
		}
		return $ret;
	}

	function serializeClass(ClassLike $class): string {
		if ($class instanceof Interface_) {
			$ret = "I" . ($this->types->add($class->namespacedName));
		} elseif ($class instanceof Node\Stmt\Enum_) {
			$ret = "E" . ($this->types->add($class->namespacedName));
		} else {
			$ret = "C" . ($this->types->add($class->namespacedName)) . ($class->flags ? " " . $class->flags : "");
		}
		if (!empty($class->extends)) {
			if ($class instanceof Interface_) {
				$ret .= " E" .
					implode(",",
							array_map(
								fn($extends) => $this->types->add($extends),
								$class->extends
							)
					);
			} else {
				$ret .= " E" . ($this->types->add($class->extends));
			}
		}
		if (($class instanceof Class_ || $class instanceof Node\Stmt\Enum_) && !empty($class->implements)) {
			$ret .= " I" . implode(',', array_map(fn($type) => $this->types->add($type), $class->implements));
		}
		$ret .= "{";
		foreach ($class->stmts as $stmt) {
			if ($stmt instanceof ClassMethod) {
				$ret .= $this->serializeMethod($stmt);
			} elseif ($stmt instanceof Property) {
				$ret .= $this->serializeProperty($stmt);
			} elseif ($stmt instanceof ClassConst) {
				$ret .= $this->serializeConst($stmt);
			} elseif ($stmt instanceof Node\Stmt\EnumCase) {
				$ret .= "E" . $stmt->name . ";";
			}
		}
		$ret .= "}";
		return $ret;
	}

	function unserializeClass(string $serializedClass): ClassLike {
		preg_match('/^([IEC])([0-9]+)( [0-9]+)?( E([0-9,]+))?( I([0-9,]+))?\{([^}]*)}$/', $serializedClass, $matches);
		$type = $matches[1];
		$name = $this->types->getString($matches[2]);
		$flags = isset($matches[3]) ? intval(trim($matches[3])) : 0;
		if (!empty($matches[5])) {
			$extends = array_map(fn($type)=>$this->types->getString(intval($type)), explode(",", $matches[5]));
		} else {
			$extends = null;
		}
		if (!empty($matches[7])) {
			$implements = array_map(fn($type)=>$this->types->getString(intval($type)), explode(",", $matches[7]));
		} else {
			$implements = [];
		}

		$parts = explode(";", $matches[8]);
		$stmts = $this->unserializeClassMembers($parts);
		if ($type == "I") {
			$cls = new Interface_($name, [
				'extends' => $extends,
				'stmts' => $stmts,
				'flags' => $flags
			]);
		} elseif ($type == "E") {
			$cls = new Node\Stmt\Enum_($name, [
				'extends' => $extends,
				'stmts' => $stmts,
				'flags' => $flags
			]);
		} else {
			$cls = new Class_($name, [
				'extends' => $extends ? $extends[0] : null,
				'implements' => $implements,
				'stmts' => $stmts,
				'flags' => $flags]);

		}
		$cls->namespacedName = $name;
		return $cls;

	}

	function unserializeMethod(string $serializedMethod): ClassMethod {
		preg_match('/^M([^ &]+)([ &])?([0-9]+)?\(([^)]*)\)(?::([0-9]+)?(?:@([0-9]+))?)?(?:T([0-9,]+))?(V)?$/', $serializedMethod, $matches);
		$name = $matches[1];
		$returnsByRef = isset($matches[2]) && $matches[2] == "&";
		$flags = !empty($matches[3]) ? intval($matches[3]) : 0;
		$parts = !empty($matches[4]) ? explode(",", $matches[4]) : [];
		$returnType = !empty($matches[5]) ? $this->types->getString(intval($matches[5])) : null;
		$params = $this->unserializeParams($parts);

		$options = [
			'byRef' => $returnsByRef,
			'flags' => $flags,
			'params' => $params
		];

		if ($returnType) {
			$options['returnType'] = $returnType;
		}
		$method = new ClassMethod($name, $options);
		if (!empty($matches[6])) {
			$method->setAttribute('namespacedReturn', $this->types->getString(intval($matches[6])));
		}
		if (!empty($matches[7])) {
			$types = array_map( fn($match) => $this->types->getString(intval($match)), explode(",", $matches[7]));
			$method->setAttribute('throws', $types);
		}
		if (!empty($matches[8])) {
			$method->setAttribute('variadic_implementation', true);
		}
		return $method;
	}

	function unserializeProperty(string $serializedProperty): Property {
		preg_match('/^(P)([^$@]+)?(?:@([0-9]+))?\\$([^ ]*)( [0-9]+)?$/', $serializedProperty, $matches);
		$type = !empty(trim($matches[2])) ? $this->types->getString(intval(trim($matches[2]))) : null;
		$name = $matches[4];
		$flags = isset( $matches[5]) ? intval(trim($matches[5])) : 0;
		$props = [new Node\Stmt\PropertyProperty($name)];
		$prop = new Property($flags, $props, type: $type );
		if (!empty($matches[3])) {
			$prop->setAttribute('DocBlockName', $this->types->getString(intval($matches[3])));
		}
		return $prop;
	}

	function unserializeConst(string $serializedConstant): ClassConst {
		preg_match('/^(C)([^ ]+)( [0-9]+( [0-9]+)?)?$/', $serializedConstant, $matches);
		$name = $matches[2];
		$flags = !empty($matches[4]) ? intval(trim($matches[4])) : 0;
		$const = new ClassConst([new Node\Const_($name, new \PhpParser\Node\Scalar\String_(""))], $flags);
		if (!empty($matches[3])) {
			$const->type = $this->types->getString(intval(trim($matches[3])));
		}
		return $const;
	}


	function unserializeFunction(string $serializedFunction): Function_ {
		preg_match('/^F([^ &]+)(&)?\(([^)]*)\)(?::([0-9]+)?(?:@([0-9]+))?)?(?:T([0-9,]+))?(V)?;$/', $serializedFunction, $matches);
		$name = new Node\Name\FullyQualified($matches[1]);
		$returnsByRef = $matches[2] == "&";
		if (trim($matches[3]) !== "") {
			$parts = explode(",", $matches[3]);
			$params = $this->unserializeParams($parts);
		} else {
			$params = [];
		}

		$options = [
			'byRef' => $returnsByRef,
			'params' => $params
		];
		if (!empty($matches[4])) {
			$options['returnType'] = $this->types->getString(intval($matches[4]));
		}

		$func = new Function_($name, $options);
		$func->namespacedName = $name;
		if (!empty($matches[5])) {
			$func->setAttribute('namespacedReturn', $this->types->getString(intval($matches[5])));
		}
		if (!empty($matches[6])) {
			$types = array_map( fn($match) => $this->types->getString(intval($match)), explode(",", $matches[6]));
			$func->setAttribute('throws', $types);
		}
		if (!empty($matches[7])) {
			$func->setAttribute('variadic_implementation', true);
		}
		return $func;
	}

	/**
	 * @param array $parts
	 * @return array
	 */
	public function unserializeClassMembers(array $parts): array {
		$stmts = [];
		foreach ($parts as $part) {
			if (strlen($part) == 0) {
				continue;
			}

			//echo "Unserialize: $part\n";
			$type = $part[0];

			switch ($type) {
				case "M":
					$method = $this->unserializeMethod($part);
					$stmts["M" . strtolower($method->name)] = $method;
					break;
				case "P":
					$property = $this->unserializeProperty($part);
					$stmts['P' . strtolower($property->props[0]->name)] = $property;
					break;
				case "C":
					$const = $this->unserializeConst($part);
					$stmts['C' . strtolower($const->consts[0]->name)] = $const;
					break;
				case "E":
					$enumCase = new Node\Stmt\EnumCase(substr($part, 1));
					$stmts['E' . strtolower($enumCase->name)] = $enumCase;
					break;
			}
		}
		//echo "Unserialized ".count($stmts)." members\n";
		return $stmts;
	}

	/**
	 * @param array $parts
	 * @return array
	 */
	public function unserializeParams(array $parts): array {
		$params = [];
		$matches = [];
		foreach ($parts as $part) {
			preg_match("/^(\\.\\.\\.)?([0-9]+)?(?:@([0-9]+))?([& ])?\\$([^ =]+)(?:=(.*))?$/", $part, $matches);
			if (!empty($matches[2])) {
				$type = $this->types->getString(intval($matches[2]));
			} else {
				$type = null;
			}

			$paramName = $matches[5];
			$param = new Node\Param(new Node\Expr\Variable($paramName), null, $type);
			if (!empty($matches[3])) {
				$param->setAttribute("DocBlockName", $this->types->getString(intval($matches[3])));
			}
			$param->variadic = !empty($matches[1]);
			$param->byRef = !empty($matches[4]) && $matches[4] == "&";
			if (!empty($matches[6])) {
				if (str_contains($matches[6], "::")) {
					list($class,$name) = explode('::', $matches[6]);
					if ($class == "") {
						$param->default = new Node\Expr\ConstFetch(new Node\Name($name));
					} else {
						$param->default = new Node\Expr\ClassConstFetch(new Node\Name($class), $name);
					}
				} else {
					$param->default = match ($matches[6]) {
						"null" => new Node\Expr\ConstFetch(new Node\Name("null")),
						"int" => new Node\Scalar\LNumber(0),
						"float" => new Node\Scalar\DNumber(0.0),
						"true" => new Node\Expr\ConstFetch(new Node\Name("true")),
						"false" => new Node\Expr\ConstFetch(new Node\Name("false")),
						"array" => new Node\Expr\Array_([]),
						default => new Node\Scalar\String_("") // Also handles "string"
					};
				}
			}
			$params[] = $param;
		}
		return $params;
	}

	/**
	 * @param Node\Param[] $params
	 * @return string
	 */
	public function serializeParams(array $params): string {
		$ret = "(";
		$index = 0;
		foreach ($params as $param) {
			if ($index++ > 0) {
				$ret .= ",";
			}
			if ($param->variadic) {
				$ret .= "...";
			}
			if ($param->type !== null) {
				$ret .= $this->types->add($param->type);
			}
			if ($param->getAttribute("DocBlockName")) {
				$ret .= "@" . $this->types->add($param->getAttribute("DocBlockName"));
			}
			$ret .= $param->byRef ? "&" : " ";
			$ret .= '$' . $param->var->name;
			if ($param->default) {
				$ret .= "=";
				if ($param->default instanceof Node\Scalar\String_) {
					$ret .= "string";
				} elseif ($param->default instanceof Node\Scalar\LNumber) {
					$ret .= "int";
				} elseif ($param->default instanceof Node\Scalar\DNumber) {
					$ret .= "float";
				} elseif ($param->default instanceof Node\Expr\ConstFetch && strcasecmp($param->default->name, "null") == 0) {
					$ret .= "null";
				} elseif ($param->default instanceof Node\Expr\Array_) {
					$ret .= "array";
				} elseif ($param->default instanceof Node\Expr\ConstFetch && strcasecmp($param->default->name, "true") == 0) {
					$ret .= "true";
				} elseif ($param->default instanceof Node\Expr\ConstFetch && strcasecmp($param->default->name, "false") == 0) {
					$ret .= "false";
				} elseif ($param->default instanceof Node\Expr\ClassConstFetch && $param->default->class instanceof Node\Name && $param->default->name instanceof Node\Identifier) {
					$ret .= $param->default->class . "::" . $param->default->name;
				} elseif ($param->default instanceof Node\Expr\ConstFetch && $param->default->name instanceof Node\Name) {
					$ret .= "::" . $param->default->name;
				} else {
					$ret .= "unknown";
				}
			}
		}
		$ret .= ")";
		return $ret;
	}
}