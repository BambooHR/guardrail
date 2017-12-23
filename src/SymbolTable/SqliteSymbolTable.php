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
class SqliteSymbolTable extends SymbolTable {

	/**
	 * @var PDO
	 */
	private $con;

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
		$this->connect();

	}

	public function disconnect() {
		$this->con = null;
	}

	public function connect() {
		$this->con = new PDO("sqlite:".$this->fileName);
		$this->init();
	}

	/**
	 * init
	 *
	 * @return void
	 */
	public function init() {
		$this->con->exec('
			create table symbol_table( name text not null, type integer not null, file text not null, has_trait int not null, data blob not null, primary key(name,type)  );
		');
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
	private function addType($name, $file, $type, $hasTrait=0, $data="") {
		$sql = "INSERT INTO symbol_table(name,file,type,has_trait,data) values(?,?,?,?,?)";
		try {
			$this->con->prepare($sql)->execute([strtolower($name), $file, $type, $hasTrait, $data]);
		} catch (PDOException $exception) {
			throw new Exception("Class $name has already been declared");
		}
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
		$sql = "SELECT file FROM symbol_table WHERE name=? and type=?";
		$statement = $this->con->prepare($sql);
		$statement->execute([strtolower($name), $type]);

		$result = $statement->fetch(Pdo::FETCH_NUM);
		if ($result) {
			return $this->adjustBasePath($result[0]);
		 } else {
			return "";
		}
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
	public function getData($name, $type=self::TYPE_CLASS) {
		$sql = "SELECT data FROM symbol_table WHERE name=?";
		$params = [strtolower($name)];
		if ($type == self::TYPE_FUNCTION) {
			$sql .= " AND type=?";
			$params[] = $type;
		} else if ($type == self::TYPE_CLASS) {
			$sql .= " AND type in (?,?)";
			$params[] = self::TYPE_CLASS;
			$params[] = self::TYPE_INTERFACE;
		}
		$statement = $this->con->prepare($sql);
		$statement->execute($params);

		$result = $statement->fetch(Pdo::FETCH_NUM);
		if ($result) {
			return self::unserializeObject($result[0]);
		} else {
			return "";
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
		$sql = 'SELECT name FROM symbol_table WHERE type=? and has_trait=1';
		$statement = $this->con->prepare($sql);
		$statement->execute([self::TYPE_CLASS]);
		while ($row = $statement->fetch(Pdo::FETCH_NUM)) {
			$ret[] = $row[0];
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
		$sql = 'UPDATE symbol_table SET data=? WHERE name=? and type=?';
		$statement = $this->con->prepare($sql);
		$statement->execute( [ $serializedString, $name, $type] );
	}

	/**
	 * removeFileFromIndex
	 *
	 * @param string $name The name
	 *
	 * @return void
	 */
	public function removeFileFromIndex($name) {
		$sql = "DELETE FROM symbol_table WHERE file=?";
		$statement = $this->con->prepare($sql);
		$statement->execute($name);
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
				$stmt->setAttribute("variadic_implementation", VariadicCheckVisitor::isVariadic($stmt->stmts) );
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
		//return ( gzdeflate( serialize( $string ) ) );
		return base64_encode( gzdeflate( serialize( $string ) ) );
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
		//return unserialize( gzinflate( ( $string ) ) );
		return unserialize( gzinflate( base64_decode( $string ) ) );
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
	public function addClass($name, Class_ $class, $file) {
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
		$clone->setAttribute("variadic_implementation", VariadicCheckVisitor::isVariadic( $function->stmts ));
		$clone->stmts = [];
		$this->addType($name, $file, self::TYPE_FUNCTION, 0, self::serializeObject($clone) );
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
}