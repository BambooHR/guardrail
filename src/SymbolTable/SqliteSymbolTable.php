<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\SymbolTable;

use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class SqliteSymbolTable
 */
class SqliteSymbolTable extends SymbolTable {
	private $con;


	function __construct($fileName, $basePath) {
		parent::__construct($basePath);
		$this->con = new \PDO("sqlite:$fileName");
		$this->init();
	}

	function init() {
		$this->con->exec('
			create table symbol_table( name text not null, type integer not null, file text not null, has_trait int not null, data blob not null, primary key(name,type)  );
		');
	}

	private function addType($name, $file, $type, $hasTrait=0, $data="") {
		$sql="INSERT INTO symbol_table(name,file,type,has_trait,data) values(?,?,?,?,?)";
		try {
			$this->con->prepare($sql)->execute([strtolower($name), $file, $type, $hasTrait, $data]);
		}
		catch(\PDOException $e) {
			throw new \Exception("Class $name has already been declared");
		}
	}

	function getType($name, $type) {
		$sql="SELECT file FROM symbol_table WHERE name=? and type=?";
		$statement=$this->con->prepare($sql);
		$statement->execute([strtolower($name), $type]);

		$result=$statement->fetch(\Pdo::FETCH_NUM);
		if($result) {
			return $this->adjustBasePath($result[0]);
	 	} else {
			return "";
		}
	}


	function getClassOrInterfaceData($name) {
		return $this->getData($name);

	}

	function getData($name, $type=self::TYPE_CLASS) {
		$sql = "SELECT data FROM symbol_table WHERE name=?";
		$params = [strtolower($name)];
		if ($type==self::TYPE_FUNCTION) {
			$sql .= " AND type=?";
			$params[]=$type;
		} else if($type==self::TYPE_CLASS) {
			$sql .= " AND type in (?,?)";
			$params[] = self::TYPE_CLASS;
			$params[] = self::TYPE_INTERFACE;
		}
		$statement = $this->con->prepare($sql);
		$statement->execute($params);

		$result=$statement->fetch(\Pdo::FETCH_NUM);
		if($result) {
			return self::unserializeObject($result[0]);
		} else {
			return "";
		}
	}

	function getInterface($name) {
		return $this->getClassOrInterface($name);
	}



	function getAbstractedFunction($name) {
		$ob=$this->cache->get("AFunction:".$name);
		if(!$ob) {
			$ob = $this->getData($name, self::TYPE_FUNCTION);
			if($ob) {
				$ob = new \BambooHR\Guardrail\Abstractions\Function_($ob);
			}  else {
				try {
					$refl = new \ReflectionFunction($name);
					$ob = new \BambooHR\Guardrail\Abstractions\ReflectedFunction($refl);
				}
				catch(\ReflectionException $e) {
					$ob = null;
				}
			}
		}
		if($ob) {
			$this->cache->add("AFunction:".$name, $ob);
		}
		return $ob;
	}

	function getClassesThatUseATrait() {
		$ret = [];
		$sql = 'SELECT name FROM symbol_table WHERE type=? and has_trait=1';
		$statement = $this->con->prepare($sql);
		$statement->execute([self::TYPE_CLASS]);
		while ($row = $statement->fetch(\Pdo::FETCH_NUM)) {
			$ret[] = $row[0];
		}
		return $ret;
	}

	function updateClass(Node\Stmt\ClassLike $class) {
		$name = strtolower($class->namespacedName);
		$clone = $this->stripMethodContents($class);
		$serializedString = self::serializeObject($clone);
		$type = $class instanceof Trait_ ? self::TYPE_TRAIT : self::TYPE_CLASS;
		$sql='UPDATE symbol_table SET data=? WHERE name=? and type=?';
		$statement = $this->con->prepare($sql);
		$statement->execute( [ $serializedString, $name, $type] );
	}

	function removeFileFromIndex($name) {
		$sql="DELETE FROM symbol_table WHERE file=?";
		$statement=$this->con->prepare($sql);
		$statement->execute($name);
	}

	function stripMethodContents(Node\Stmt\ClassLike $class) {
		// Make a deep copy and then remove implementation code (to save space).
		$clone=unserialize(serialize($class));
		foreach($clone->stmts as $index=>&$stmt) {
			if($stmt instanceof Node\Stmt\ClassMethod) {
				$stmt->setAttribute("variadic_implementation",VariadicCheckVisitor::isVariadic($stmt->stmts) );
				$stmt->stmts = [];
			}
		}
		return $clone;
	}

	/**
	 * PHP's serialize() is very fast, but it produces a bloated serialization string.  We deflate it to make it 10x smaller
	 * Then we base64_encode to make it a little bit safer to deal with in the db layer.
	 * @param $string
	 * @return string
	 */
	private static function serializeObject($string) {
		//return ( gzdeflate( serialize( $string ) ) );
		return base64_encode( gzdeflate( serialize( $string ) ) );
		//return serialize( $string );
	}

	/**
	 * @param $string
	 * @return mixed
	 */
	private static function unserializeObject($string) {
		//return unserialize( gzinflate( ( $string ) ) );
		return unserialize( gzinflate( base64_decode( $string ) ) );
		//return unserialize( $string );
	}

	function addClass($name, Class_ $class, $file) {
		$usesTrait = 0;
		foreach($class->stmts as $stmt) {
			if($stmt instanceof Node\Stmt\TraitUse) {
				$usesTrait = 1;
			}
		}
		$clone = $this->stripMethodContents($class);
		$this->addType($name, $file, self::TYPE_CLASS, $usesTrait, self::serializeObject($clone));
	}

	/**
	 * @param $name
	 * @return \BambooHR\Guardrail\Abstractions\Class_
	 */
	function getAbstractedClass($name) {
		$cacheName=strtolower($name);
		$ob=$this->cache->get("AClass:".$cacheName);
		if(!$ob) {
			$tmp = $this->getClassOrInterfaceData($name);
			if ($tmp) {
				$ob = new \BambooHR\Guardrail\Abstractions\Class_($tmp);
			} else if (strpos($name, "\\") === false) {
				try {
					$refl = new \ReflectionClass($name);
					$ob = new \BambooHR\Guardrail\Abstractions\ReflectedClass($refl);
				} catch (\ReflectionException $e) {
					$ob = null;
				}
			}
			if ($ob) {
				$this->cache->add("AClass:" . $cacheName, $ob);
			}
		}
		return $ob;
	}

	function addInterface($name, Interface_ $interface, $file) {
		$this->addType($name, $file, self::TYPE_INTERFACE, 0, self::serializeObject($interface));
	}

	function addFunction($name, Function_ $function, $file) {
		$clone = clone $function;
		$clone->setAttribute("variadic_implementation", VariadicCheckVisitor::isVariadic( $function->stmts ));
		$clone->stmts = [];
		$this->addType($name, $file, self::TYPE_FUNCTION, 0 , self::serializeObject($clone) );
	}

	function addTrait($name, Trait_ $trait, $file) {
		$this->addType($name, $file, self::TYPE_TRAIT);
	}

	function addDefine($name, Node $define, $file) {
		$this->addType($name, $file, self::TYPE_DEFINE);
	}

	function getDefineFile($name) {
		return $this->getType($name, self::TYPE_DEFINE);
	}

	function getTraitFile($name) {
		return $this->getType($name, self::TYPE_TRAIT);
	}

	function getClassFile($className) {
		return $this->getType($className, self::TYPE_CLASS);
	}

	function getInterfaceFile($interfaceName) {
		return $this->getType($interfaceName, self::TYPE_INTERFACE);
	}

	function getFunctionFile($functionName) {
		return $this->getType($functionName, self::TYPE_FUNCTION);
	}
}