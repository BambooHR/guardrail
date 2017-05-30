<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use Seld\JsonLint\JsonParser;
use Webmozart\Glob\Glob;

/**
 * Class Util
 *
 * @package BambooHR\Guardrail
 */
class Util {

	/**
	 * finalPart
	 *
	 * @param Object $parts The class we are checking
	 *
	 * @return mixed
	 */
	static public function finalPart( $parts ) {
		return property_exists($parts, "parts") && is_array($parts->parts) ? $parts->parts[count($parts->parts) - 1] : $parts;
	}

	/**
	 * isScalarType
	 *
	 * @param string $name The scalar type
	 *
	 * @return bool
	 */
	static public function isScalarType($name) {
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
	static public function isLegalNonObject($name) {
		return self::isScalarType($name) || strcasecmp($name, "callable") == 0 || strcasecmp($name, "array") == 0;
	}

	/**
	 * methodSignatureString
	 *
	 * @param ClassMethod $method Instance of ClassMethod
	 *
	 * @return string
	 */
	static public function methodSignatureString(ClassMethod $method) {
		$ret = [];
		foreach ($method->params as $param) {
			$ret[] = $param->type ? static::finalPart($param->type) : '$' . $param->name;
		}
		return static::finalPart($method->name) . "(" . implode(",", $ret) . ")";
	}

	/**
	 * getMethodAccessLevel
	 *
	 * @param ClassMethod $level Instance of ClassMethod
	 *
	 * @return string
	 */
	static public function getMethodAccessLevel(ClassMethod $level) {
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
	 * @param string $globArr  The rest
	 *
	 * @return bool
	 */
	static public function matchesGlobs($basePath, $path, $globArr) {
		foreach ($globArr as $glob) {
			if ($glob[0] == '/') {
				if (Glob::match($path, $glob)) {
					return true;
				}
			} else {
				if (Glob::match($path, $basePath . "/" . $glob)) {
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
	 * @return bool|string
	 */
	static public function removeInitialPath($path, $name) {
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
	 * @return null|\BambooHR\Guardrail\Abstractions\Class_|\BambooHR\Guardrail\Abstractions\ClassMethod|\BambooHR\Guardrail\Abstractions\ReflectedClassMethod
	 */
	static public function findAbstractedMethod($className, $name, SymbolTable $symbolTable) {
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
	 * @param string             $className
	 * @param string            $name
	 * @param SymbolTable $symbolTable
	 *
	 * @return null|\BambooHR\Guardrail\Abstractions\Property
	 */
	static public function findAbstractedProperty($className, $name, SymbolTable $symbolTable) {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return null;
			}

			$method = $class->getProperty($name);
			if ($method) {
				return $method;
			}
			$className = $class->getParentClassName();
		}
		return null;
	}

	static public function findAbstractedSignature($className, $name, SymbolTable $symbolTable) {
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

	static public function callIsCompatible(ClassMethod $method,MethodCall $call) {

	}

	/**
	 * configDirectoriesAreValid
	 *
	 * @param string $baseDirectory The base directory
	 * @param array  $paths         The list of paths to test from the config file
	 *
	 * @return bool
	 */
	static public function configDirectoriesAreValid($baseDirectory, $paths) {
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
	static public function fullDirectoryPath($baseDirectory, $path) {
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
	static public function jsonFileContentIsValid($jsonFile) {
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
}



