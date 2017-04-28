<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\SymbolTable;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Expr\FuncCall;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class SqliteSymbolTable
 */
class SqliteSymbolTable extends SymbolTable {
	private $con;
	const TYPE_CLASS=1;
	const TYPE_FUNCTION=2;
	const TYPE_INTERFACE=3;
	const TYPE_TRAIT=4;
	const TYPE_DEFINE=5;

	function __construct($fileName, $basePath) {
		parent::__construct($basePath);
		$this->con = new \PDO("sqlite:$fileName");
		$this->init();
	}

	function init() {
		$this->con->exec('
			create table symbol_table( name text not null, type integer not null, file text not null, primary key(name,type)  );
		');
	}

	private function addType($name, $file, $type) {
		$sql="INSERT INTO symbol_table(name,file,type) values(?,?,?)";
		try {
			$this->con->prepare($sql)->execute([strtolower($name), $file, $type]);
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

	function removeFileFromIndex($name) {
		$sql="DELETE FROM symbol_table WHERE file=?";
		$statement=$this->con->prepare($sql);
		$statement->execute($name);
	}


	function addClass($name, Class_ $class, $file) {
		$this->addType($name, $file, self::TYPE_CLASS);
	}

	function addInterface($name, Interface_ $interface, $file) {
		$this->addType($name, $file, self::TYPE_INTERFACE);
	}

	function addFunction($name, Function_ $function, $file) {
		$this->addType($name, $file, self::TYPE_FUNCTION);
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