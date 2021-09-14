<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassMethod;
use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use Seld\JsonLint\JsonParser;
use Webmozart\Glob\Glob;

/**
 * Class Util
 *
 * @package BambooHR\Guardrail
 */
class Util {

	/**
	 * isScalarType
	 *
	 * @param string $name The scalar type
	 *
	 * @return bool
	 */
	static public function isScalarType($name):bool {
		$name = strtolower($name);
		return $name == 'bool' || $name == 'string' || $name == 'int' || $name == 'float';
	}

	/**
	 * isLegalNonObject
	 *
	 * @param string $name The scalar type
	 *
	 * @return bool
	 */
	static public function isLegalNonObject($name):bool {
		return self::isScalarType($name) || strcasecmp($name, "callable") == 0 || strcasecmp($name, "iterable") == 0 || strcasecmp($name, "array") == 0 || strcasecmp($name, "void") == 0 || strcasecmp($name, "null") == 0 || strcasecmp($name,"resource") == 0 || strcasecmp($name,"object")==0;
	}


	/**
	 * getMethodAccessLevel
	 *
	 * @param ClassMethod $level Instance of ClassMethod
	 *
	 * @return string
	 */
	static public function getMethodAccessLevel(ClassMethod $level):string {
		if ($level->isPublic()) {
			return "public";
		}
		if ($level->isPrivate()) {
			return "private";
		}
		if ($level->isProtected()) {
			return "protected";
		}
		trigger_error("Impossible");
	}

	/**
	 * matchesGlobs
	 *
	 * @param string $basePath The base path
	 * @param string $path     The path
	 * @param array  $globArr  The rest
	 *
	 * @return bool
	 */
	static public function matchesGlobs(string $basePath, string $path, array $globArr):bool {
		foreach ($globArr as $glob) {
			if ($glob[0] == '/') {
				if (Glob::match($path, $glob)) {
					return true;
				}
			} else {
				if (Glob::match($path, $basePath . $glob)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * removeInitialPath
	 *
	 * @param string $path The path
	 * @param string $name The name
	 *
	 * @return string
	 */
	static public function removeInitialPath($path,$name):string {
		if (strpos($name, $path) === 0) {
			$name = substr($name, strlen($path));
			while ($name[0] == "/") {
				$name = substr($name, 1);
			}
			return $name;
		} else {
			return $name;
		}
	}

	/**
	 * findAbstractedMethod
	 *
	 * @param string      $className   The class name
	 * @param string      $name        The method name
	 * @param SymbolTable $symbolTable Instance of SymbolTable
	 *
	 * @return null|MethodInterface
	 */
	static public function findAbstractedMethod(string $className,string $name, SymbolTable $symbolTable):?MethodInterface {
		$className = strval($className);
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return null;
			}

			$method = $class->getMethod($name);
			if ($method) {
				return $method;
			}
			$className = $class->getParentClassName();
		}
		return null;
	}

	/**
	 * findAbstractedProperty
	 *
	 * @param string      $className   The class name
	 * @param string      $name        The name
	 * @param SymbolTable $symbolTable Instance of SymbolTable
	 *
	 * @return array First param is the abstracted method, second param is the class it was declared in.
	 */
	static public function findAbstractedProperty(string $className, string $name, SymbolTable $symbolTable):array {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return [null,""];
			}

			$property = $class->getProperty($name);
			if ($property) {
				return [$property, $className];
			}
			$className = $class->getParentClassName();
		}
		return [null,""];
	}

	/**
	 * findAbstractedSignature
	 *
	 * @param string      $className   The class name
	 * @param string      $name        The name
	 * @param SymbolTable $symbolTable Instance of SymbolTable
	 *
	 * @return MethodInterface|null
	 */
	static public function findAbstractedSignature(string $className, string $name, SymbolTable $symbolTable):?MethodInterface  {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return null;
			}

			$method = $class->getMethod($name);
			if ($method) {
				return $method;
			}
			foreach ($class->getInterfaceNames() as $interfaceName) {
				$method = self::findAbstractedSignature($interfaceName, $name, $symbolTable);
				if ($method) {
					return $method;
				}
			}
			$className = $class->getParentClassName();
		}
		return null;
	}


	/**
	 * configDirectoriesAreValid
	 *
	 * @param string $baseDirectory The base directory
	 * @param array  $paths         The list of paths to test from the config file
	 *
	 * @return bool
	 */
	static public function configDirectoriesAreValid(string $baseDirectory,array $paths):bool {
		if (is_object($baseDirectory) || !is_array($paths) || empty($paths)) {
			throw new \InvalidArgumentException('The config data is bad');
		}
		$results = true;
		foreach ($paths as $path) {
			$location = static::fullDirectoryPath($baseDirectory, $path);

			if (! is_dir($location)) {
				$results = false;
			}
		}
		return $results;
	}

	/**
	 * fullDirectoryPath
	 *
	 * @param string $baseDirectory The base directory from the config
	 * @param string $path          The path we are checking
	 *
	 * @return string
	 */
	static public function fullDirectoryPath(string $baseDirectory, string $path):string {
		$baseDirectory = substr($baseDirectory, -1) === '/' ? $baseDirectory : $baseDirectory . '/';
		return strpos($path, "/") === 0 ? $path : $baseDirectory . $path;
	}

	/**
	 * jsonFileContentIsValid
	 *
	 * @param string $jsonFile The path to the json file to validate
	 *
	 * @return array
	 */
	static public function jsonFileContentIsValid(string $jsonFile):array {
		$status = ['success' => true, 'message' => 'json is valid'];
		if (!file_exists($jsonFile)) {
			throw new \InvalidArgumentException('File does not exist.');
		}
		$parser = new JsonParser();
		$results = $parser->lint(file_get_contents($jsonFile));
		if (null !== $results) {
			$status = ['success' => false, 'message' => $results->getMessage()];
		}
		return $status;
	}

	/**
	 * allBranchesExit
	 *
	 * @param array $stmts List of statements
	 *
	 * @return bool
	 */
	static public function allBranchesExit(array $stmts):bool {
		$lastStatement = self::getLastStatement($stmts);

		if (!$lastStatement) {
			return false;
		} else if ($lastStatement instanceof Expression && $lastStatement->expr instanceof Exit_) {
			return true;
		} else if ($lastStatement instanceof Return_) {
			return true;
		} else if ($lastStatement instanceof If_) {
			return self::allIfBranchesExit($lastStatement);
		} else if ($lastStatement instanceof Switch_) {
			return self::allSwitchCasesExit($lastStatement);
		} else {
			return false;
		}
	}

	/**
	 * getLastStatement
	 *
	 * @param array $stmts The statements
	 *
	 * @return mixed|null
	 */
	static public function getLastStatement(array $stmts):?Stmt {
		$lastStatement = null;
		foreach ($stmts as $stmt) {
			if (!$stmt instanceof Nop) {
				$lastStatement = $stmt;
			}
		}
		return $lastStatement;
	}

	/**
	 * allIfBranchesExit
	 *
	 * @param If_ $lastStatement Instance of If_
	 *
	 * @return bool
	 */
	static protected function allIfBranchesExit(If_ $lastStatement):bool {
		if (!$lastStatement->else && !$lastStatement->elseifs) {
			return false;
		}
		$trueCond = self::allBranchesExit($lastStatement->stmts);
		if (!$trueCond) {
			return false;
		}
		if ($lastStatement->else && !self::allBranchesExit($lastStatement->else->stmts)) {
			return false;
		}
		if ($lastStatement->elseifs) {
			foreach ($lastStatement->elseifs as $elseIf) {
				if (!self::allBranchesExit($elseIf->stmts)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * allSwitchCasesExit
	 *
	 * @param Switch_ $lastStatement Instance of Switch_
	 *
	 * @return bool
	 */
	static protected function allSwitchCasesExit(Switch_ $lastStatement):bool {
		$hasDefault = false;
		foreach ($lastStatement->cases as $case) {
			if (!$case->cond) {
				$hasDefault = true;
			}
			$stmts = $case->stmts;
			// Remove the trailing break (if found) and just look for a return the statement prior
			while ( ($last = end($stmts)) instanceof Break_ || $last instanceof Nop) {
				$stmts = array_slice($stmts, 0, -1);
			}
			if ($stmts && !self::allBranchesExit($stmts)) {
				return false;
			}
		}
		return $hasDefault;
	}
}



