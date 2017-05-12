<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Property;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Abstractions;
use Webmozart\Glob\Glob;

class Util {

	static function finalPart( $parts ) {
		return property_exists($parts,"parts") && is_array($parts->parts) ? $parts->parts[ count($parts->parts)-1 ] : $parts;
	}

	static function isScalarType($name) {
		$name=strtolower($name);
		return $name=='bool' || $name=='string' || $name=='int' || $name=='float';
	}

	static function isLegalNonObject($name) {
		return Util::isScalarType($name) || strcasecmp($name,"callable")==0 || strcasecmp($name,"array")==0;
	}

	static function methodSignatureString(ClassMethod $method) {
		$ret = [];
		foreach($method->params as $param) {
			$ret[]=$param->type ? static::finalPart($param->type) : '$'.$param->name;
		}
		return static::finalPart($method->name)."(".implode(",", $ret).")";
	}
	static function getMethodAccessLevel(ClassMethod $level) {
		if($level->isPublic()) return "public";
		if($level->isPrivate()) return "private";
		if($level->isProtected()) return "protected";
		trigger_error("Impossible");
	}

	static function matchesGlobs($basePath, $path, $globArr) {
		foreach($globArr as $glob) {
			if($glob[0]=='/') {
				if(Glob::match($path, $glob)) {
					return true;
				}
			} else {
				if(Glob::match($path, $basePath."/".$glob)) {
					return true;
				}
			}
		}
		return false;
	}

	static function removeInitialPath($path, $name) {
		if(strpos($name,$path)===0) {
			$name = substr($name,strlen($path));
			while($name[0]=="/") {
				$name=substr($name,1);
			}
			return $name;
		} else {
			return $name;
		}
	}

	/**
	 * @param             $className
	 * @param             $name
	 * @param SymbolTable $symbolTable
	 * @return null|\BambooHR\Guardrail\Abstractions\Class_|\BambooHR\Guardrail\Abstractions\ClassMethod|\BambooHR\Guardrail\Abstractions\ReflectedClassMethod
	 */
	static function findAbstractedMethod($className, $name, SymbolTable $symbolTable) {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if(!$class) {
				return null;
			}

			$method = $class->getMethod($name);
			if($method) {
				return $method;
			}
			$className=$class->getParentClassName();
		}
		return null;
	}

	/**
	 * @param             $className
	 * @param             $name
	 * @param SymbolTable $symbolTable
	 * @return null|\BambooHR\Guardrail\Abstractions\Class_|\BambooHR\Guardrail\Abstractions\ClassMethod|\BambooHR\Guardrail\Abstractions\ReflectedClassMethod
	 */
	static function findAbstractedProperty($className, $name, SymbolTable $symbolTable) {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if(!$class) {
				return null;
			}

			$method = $class->getProperty($name);
			if($method) {
				return $method;
			}
			$className=$class->getParentClassName();
		}
		return null;
	}

	static function findAbstractedSignature($className, $name, SymbolTable $symbolTable) {
		while ($className) {
			$class = $symbolTable->getAbstractedClass($className);
			if(!$class) {
				return null;
			}

			$method = $class->getMethod($name);
			if($method) {
				return $method;
			}
			foreach($class->getInterfaceNames() as $interfaceName) {
				$method = self::findAbstractedSignature($interfaceName, $name, $symbolTable);
				if($method) {
					return $method;
				}
			}
			$className=$class->getParentClassName();
		}
		return null;
	}

	static function callIsCompatible(ClassMethod $method,MethodCall $call) {

	}
}



