<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */


use BambooHR\Guardrail\Abstractions\Property;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassMethod;
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
	 * finalPart
	 *
	 * @param Object $parts The class we are checking
	 *
	 * @return "mixed"
	 */
	static public function finalPart( $parts ) {
		return property_exists($parts, "parts") && is_array($parts->parts) ? $parts->parts[count($parts->parts) - 1] : $parts;
	}

	static function mapClassName(string $name, string $selfName, string $staticName):string {
		if(strcasecmp($name,'static')==0) {
			return $staticName;
		} else if(strcasecmp($name,'self')==0) {
			return $selfName;
		} else {
			return $name;
		}
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
		return $name == 'bool' || $name == 'string' || $name == 'int' || $name == 'float' || $name=="false" || $name =="true";
	}


	static function getPhpAttribute(string $name, array $attrGroups):?Attribute {
		foreach($attrGroups as $attrGroup) {
			/** @var AttributeGroup $attrGroup */
			foreach($attrGroup->attrs as $attribute) {
				if (strcasecmp($name, $attribute->name)==0) {
					return $attribute;
				}
			}
		}
		return null;
	}
	/**
	 * isLegalNonObject
	 *
	 * @param string $name The scalar type
	 *
	 * @return bool
	 */
	static public function isLegalNonObject($name) {
		return self::isScalarType($name) || strcasecmp($name,"mixed") == 0 || strcasecmp($name, "callable") == 0 || strcasecmp($name, "iterable") == 0 || strcasecmp($name, "array") == 0 || strcasecmp($name, "void") == 0 || strcasecmp($name, "null") == 0 || strcasecmp($name,"resource") == 0 || strcasecmp($name,"object")==0;
	}

	static public function isSelfOrStaticType(string $name):bool {
		return strcasecmp($name, "self")== 0 || strcasecmp($name,"static") ==0;
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
		return "";
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
	static public function matchesGlobs($basePath, $path, $globArr) {
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

	static public function reflectionTypeToPhpParserType(?\ReflectionType $type) {
		if ($type instanceof \ReflectionNamedType) {
			if($type->isBuiltin()) {
				return TypeComparer::identifierFromName($type->getName());
			} else {
				return TypeComparer::nameFromName($type->getName());
			}
		} else if ($type instanceof \ReflectionUnionType) {
			$subtypes = array_map(
				fn($subtype)=> self::reflectionTypeToPhpParserType($subtype),
				$type->getTypes()
			);
			if($type->allowsNull()) {
				$subtypes[]=TypeComparer::identifierFromName("null");
			}
			return TypeComparer::getUniqueTypes($subtypes);
		} else if ($type instanceof \ReflectionIntersectionType) {
			$subtypes = array_map(
				fn($subtype)=> self::reflectionTypeToPhpParserType($subtype),
				$type->getTypes()
			);
			return new IntersectionType( [$subtypes] );
		} else if ($type==null) {
			return null;
		} else {
			throw new \InvalidArgumentException();
		}
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
	 * @return null|\BambooHR\Guardrail\Abstractions\ClassAbstraction|\BambooHR\Guardrail\Abstractions\ClassMethod|\BambooHR\Guardrail\Abstractions\ReflectedClassMethod
	 */
	static public function findAbstractedMethod($className, $name, SymbolTable $symbolTable) {
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

	static public function findAllInterfaces(string $className, SymbolTable $symbolTable):array {
		$interfaces = [];
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return $interfaces;
			}
			$immediateList = $class->getInterfaceNames();
			foreach($immediateList as $immediate ) {
				$childInterfaces = static::findAllInterfaces($immediate, $symbolTable);
				$interfaces = [ ...$childInterfaces, $immediate, ...$interfaces];
			}
			$className = $class->getParentClassName();
		}
 		return array_unique($interfaces);
	}

	static function findAbstractedConstantExpr(string $className, string $constantName, SymbolTable $symbolTable) {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return null;
			}

			$constant = $class->getConstantExpr($constantName);
			if ($constant) {
				return $constant;
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
	static public function findAbstractedProperty(string $className, string $name, SymbolTable $symbolTable):?Property {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if (!$class) {
				return null;
			}

			$prop = $symbolTable->getAbstractedProperty($class, $name);
			if ($prop) {
				return $prop;
			}

			$className=$class->getParentClassName();
		}
		return null;
	}

	/**
	 * findAbstractedSignature
	 *
	 * @param string      $className   The class name
	 * @param string      $name        The name
	 * @param SymbolTable $symbolTable Instance of SymbolTable
	 *
	 * @return Abstractions\ClassMethod|null
	 */
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

	/**
	 * callIsCompatible
	 *
	 * @param ClassMethod $method Instance of ClassMethod
	 * @param MethodCall  $call   Instance of MethodCall
	 *
	 * @return void
	 */
	static public function callIsCompatible(ClassMethod $method,MethodCall $call) {

	}

	static public function getFilteredChildClasses(SymbolTable $table, string $parent, string ...$potentialChildren):array {
		$ret=[];
		foreach($potentialChildren as $potentialChild) {
			if ($table->isParentClassOrInterface($parent, $potentialChild)) {
				$ret[] = $potentialChild;
			}
		}
		return $ret;
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

	/**
	 * allBranchesExit
	 *
	 * @param array $stmts List of statements
	 *
	 * @return bool
	 */
	static public function allBranchesExit(array $stmts) {
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
	static public function getLastStatement(array $stmts) {
		for (end($stmts); key($stmts)!==null; prev($stmts)){
			$currentElement = current($stmts);
			if (!$currentElement instanceof Nop) {
				return $currentElement;
			}
		}
		return null;
	}

	/**
	 * allIfBranchesExit
	 *
	 * @param If_ $lastStatement Instance of If_
	 *
	 * @return bool
	 */
	static protected function allIfBranchesExit(If_ $lastStatement) {
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
	static protected function allSwitchCasesExit(Switch_ $lastStatement) {
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

	/**
	 * @return string[]
	 */
	static function getPhpGlobalNames(): array {
		return [
			"GLOBALS", "_GET", "_POST", "_COOKIE",
			"_REQUEST", "_SERVER", "_SESSION", "_FILES",
			"http_response_header"
		];
	}

	static public function valueToExpression(mixed $value): ?Expr {
		return match (gettype($value)) {
			'boolean' => new ConstFetch(new Name($value ? 'true' : 'false')),
			'integer' => new LNumber($value),
			'double'  => new DNumber($value),
			'string'  => new String_($value),
			'array'   => self::arrayToExpression($value),
			'NULL'    => new ConstFetch(new Name('null')),
			default   => null, // Handles invalid types like resources or objects
		};
	}

	static private function arrayToExpression(array $values): ?Expr {
		$items = [];
		foreach ($values as $key => $value) {
			$itemValue = self::valueToExpression($value);
			if ($itemValue === null) {
				return null;
			}
			$itemKey = self::valueToExpression($key);
			$items[] = new ArrayItem($itemValue, $itemKey);
		}
		return new Array_($items);
	}
}



