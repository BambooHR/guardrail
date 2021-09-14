<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Abstractions\MethodInterface;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Class_;
use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractedClass_;

/**
 * Class InterfaceCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class InterfaceCheck extends BaseCheck {
	/**
	 * Visibility level map
	 *
	 * @var array
	 */
	static private $methodVisibilityLevels = [
		'private' => 0,
		'protected' => 1,
		'public' => 2,
	];

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes(): array {
		return [Class_::class];
	}

	/**
	 * checkMethod
	 *
	 * @param string          $fileName     The file name
	 * @param Class_          $class        Instance of ClassAbstraction
	 * @param MethodInterface $method       Instance of MethodInterface
	 * @param MethodInterface $parentMethod Instance of MethodInterface
	 * @guardrail-ignore Standard.Unknown.Property
	 *
	 * @return void
	 */
	protected function checkMethod($fileName, Class_ $class, MethodInterface $method, MethodInterface $parentMethod) {

		$visibility = $method->getAccessLevel();
		$oldVisibility = $parentMethod->getAccessLevel();

		$className = (isset($class->namespacedName) ? strval($class->namespacedName) : "anonymous class");

		// "public" and "protected" cannot be redefined, but private can.
		if (self::$methodVisibilityLevels[$visibility] < self::$methodVisibilityLevels[$oldVisibility]) {
			$this->emitError($fileName, $class, self::TYPE_SIGNATURE_TYPE, "Access level mismatch in " . $method->getName() . "() " . $visibility . " vs " . $oldVisibility);
		}

		$params = $method->getParameters();
		$parentMethodParams = $parentMethod->getParameters();
		$count1 = count($params);
		$count2 = count($parentMethodParams);
		if ($count1 < $count2) {
			$this->emitError($fileName, $class, self::TYPE_SIGNATURE_COUNT, "Parameter count mismatch $count1 vs $count2 in method " . $className . "->" . $method->getName());
		} else {
			foreach ($params as $index => $param) {
				/** @var FunctionLikeParameter $param */
				// Only parameters specified by the parent need to match.  (Child can add more as long as they have a default.)
				if ($index < $count2) {
					$parentParam = $parentMethodParams[$index];
					$name1 = strval($param->getType());
					$name2 = strval($parentParam->getType());
					if ($oldVisibility !== 'private' && strcasecmp($name1, $name2) !== 0) {
						$name1 = empty($name1) ? '(no parameter)' : $name1;
						$name2 = empty($name2) ? '(no parameter)' : $name2;
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Parameter mismatch type mismatch " . $className . "::" . $method->getName() . " : $name1 vs $name2");
						break;
					}
					if ($param->isReference() != $parentParam->isReference()) {
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child Method " . $className . "::" . $method->getName() . " add or removes & in \$" . $param->getName());
						break;
					}
					if (! $param->isOptional() && $parentParam->isOptional()) {
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method " . $className . "::" . $method->getName() . " changes parameter \$" . $param->getName() . " to be required.");
						break;
					}
				} else {
					if (! $param->isOptional()) {
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method " . $method->getName() . " adds parameter \$" . $param->getName() . " that doesn't have a default value");
						break;
					}
				}
			}
		}
	}

	/**
	 * implementsMethod
	 *
	 * @param Class_ $node            Instance of ClassAbstraction
	 * @param string $interfaceMethod The interface
	 *
	 * @return ClassMethod|null
	 */
	protected function implementsMethod(Class_ $node, $interfaceMethod) {
		$current = new AbstractedClass_($node);
		while (true) {
			// Is it directly in the class
			$classMethod = $current->getMethod($interfaceMethod);
			if ($classMethod) {
				return $classMethod;
			}

			if ($current->getParentClassName()) {
				$current = $this->symbolTable->getAbstractedClass($current->getParentClassName());
			} else {
				return null;
			}
		}
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node Instance of the Node
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run(string $fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node instanceof Class_) {
			if ($node->implements) {
				$this->processNodeImplements($fileName, $node);
			}
			if ($node->extends) {
				$this->processNodeExtends($fileName, $node);
			}
		}
	}

	/**
	 * processNodeImplements
	 *
	 * @param string $fileName The filename
	 * @param Node   $node     Instance of Node
	 *
	 * @return void
	 */
	private function processNodeImplements($fileName, Class_ $node) {
		$arr = is_array($node->implements) ? $node->implements : [$node->implements];
		foreach ($arr as $interface) {
			$name = $interface->toString();
			if ($name) {
				$interface = $this->symbolTable->getAbstractedClass($name);
				if (! $interface) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, $node->name . " implements unknown interface " . $name);
				} else {
					$this->processNodeImplementsNotAbstract($fileName, $node, $interface);
				}
			}
		}
	}

	/**
	 * processNodeExtends
	 *
	 * @param string $fileName The file name
	 * @param Node   $node     Instance of Node
	 *
	 * @return void
	 */
	private function processNodeExtends($fileName, Class_ $node) {
		$class = new AbstractedClass_($node);
		$parentClass = $this->symbolTable->getAbstractedClass($node->extends);
		if (! $parentClass) {
			$this->emitError($fileName, $node->extends, ErrorConstants::TYPE_UNKNOWN_CLASS, "Unable to find parent " . $node->extends);
		}

		/*
		$str = "Checking ". $class->getName()."\n";
		echo $str;
		$coreDomain = $this->symbolTable->isParentClassOrInterface("Core\\Domain\\Domain", $class->getName());
		$bambooHrDomain = $this->symbolTable->isParentClassOrInterface("BambooHR\\Domain\\Domain", $class->getName());
		$type = ($coreDomain ? 1 : ($bambooHrDomain ? 2 : 0) );
		if ($type) {
			$method = Util::findAbstractedMethod($class->getName(), "__construct", $this->symbolTable);
			if ($method) {
				$log = fopen("/tmp/class_stats.csv", "a");
				fputcsv($log, [$class->getName(), count($method->getParameters()), $type]);
				fclose($log);
			}
		}
		*/
		foreach ($class->getMethodNames() as $methodName) {
			if ($methodName != "__construct") {
				$method = Util::findAbstractedMethod($node->extends, $methodName, $this->symbolTable);
				if ($method) {
					$this->checkMethod($fileName, $node, $class->getMethod($methodName), $method);
				}
			}
		}
	}

	/**
	 * processNodeImplementsNotAbstract
	 *
	 * @param string $fileName  The file name
	 * @param Node   $node      Instance of Node
	 * @param string $interface The interface
	 *
	 * @return void
	 */
	private function processNodeImplementsNotAbstract($fileName, Class_ $node, $interface) {
		// Don't force abstract classes to implement all methods.
		if (! $node->isAbstract()) {
			foreach ($interface->getMethodNames() as $interfaceMethod) {
				$classMethod = $this->implementsMethod($node, $interfaceMethod);
				if (! $classMethod) {
					if (! $node->isAbstract()) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNIMPLEMENTED_METHOD, $node->name . " does not implement method " . $interfaceMethod);
					}
				} else {
					$this->checkMethod($fileName, $node, $classMethod, $interface->getMethod($interfaceMethod));
				}
			}
		}
	}
}
