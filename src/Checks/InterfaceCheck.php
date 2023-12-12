<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractedClass_;
use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;

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
	public function getCheckNodeTypes() {
		return [Class_::class];
	}

	/**
	 * checkMethod
	 *
	 * @param string          $fileName     The file name
	 * @param Class_          $class        Instance of ClassAbstraction
	 * @param MethodInterface $method       Instance of MethodInterface
	 * @param MethodInterface $parentMethod Instance of MethodInterface
	 *
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

		$this->assertParentChildReturnTypesMatch($method, $parentMethod, $fileName, $className);

		$params = $method->getParameters();
		$parentMethodParams = $parentMethod->getParameters();
		$childParameterCount = count($params);
		$parentParameterCount = count($parentMethodParams);
		if ($childParameterCount < $parentParameterCount) {
			$this->emitError($fileName, $class, self::TYPE_SIGNATURE_COUNT, "Parameter count mismatch $childParameterCount vs $parentParameterCount in method " . $className . "->" . $method->getName());
		} else {
			foreach ($params as $index => $childParam) {
				/** @var FunctionLikeParameter $childParam */
				// Only parameters specified by the parent need to match.  (Child can add more as long as they have a default.)
				if ($index < $parentParameterCount) {
					$parentParam = $parentMethodParams[$index];
					$childParamType = TypeComparer::typeToString($childParam->getType());
					$parentParamType = TypeComparer::typeToString($parentParam->getType());

					if ($oldVisibility !== 'private' && (strcasecmp($childParamType, $parentParamType) !== 0 && $childParamType !== 'mixed')) {
						$childParamType = empty($childParamType) ? '(unspecified)' : $childParamType;
						$parentParamType = empty($parentParamType) ? '(unspecified)' : $parentParamType;
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method parameter #$index type mismatch " . $className . "::" . $method->getName() . " : $childParamType vs $parentParamType");
						break;
					}
					if ($childParam->isReference() != $parentParam->isReference()) {
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method " . $className . "::" . $method->getName() . " add or removes & in \$" . $childParam->getName());
						break;
					}
					if (!$childParam->isOptional() && $parentParam->isOptional()) {
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method " . $className . "::" . $method->getName() . " changes parameter \$" . $childParam->getName() . " to be required.");
						break;
					}
				} else {
					if (!$childParam->isOptional()) {
						$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method " . $method->getName() . " adds parameter \$" . $childParam->getName() . " that doesn't have a default value");
						break;
					}
				}
			}
		}
	}

	/**
	 * @param MethodInterface $childMethod
	 * @param MethodInterface $parentMethod
	 * @param string          $fileName
	 * @param string          $className
	 *
	 * @return void
	 */
	private function assertParentChildReturnTypesMatch(
		MethodInterface $childMethod,
		MethodInterface $parentMethod,
		string          $fileName,
		string          $className
	) {
		$parentReturnTypes = $this->getReturnTypesForMethod($parentMethod);
		$childReturnTypes = $this->getReturnTypesForMethod($childMethod);
		$childReturnTypes = $this->updateChildReturnTypesToAccountForCovariance($childReturnTypes, $parentReturnTypes);

		$differentChildReturnTypes = array_diff($childReturnTypes, $parentReturnTypes);
		if (!empty($differentChildReturnTypes) && !in_array("mixed", $parentReturnTypes)) {
			$diffTypesStr = implode(", and", $differentChildReturnTypes);
			$this->emitErrorOnLine($fileName, $childMethod->getStartingLine(), self::TYPE_SIGNATURE_RETURN,
				"Child method return types do not match parent return types" . $className . "::" .
				$childMethod->getName() . " : Child can return $diffTypesStr and parent cannot."
			);
		}
	}

	/**
	 * @param array $childReturnTypes
	 * @param array $parentReturnTypes
	 *
	 * @return array
	 */
	private function updateChildReturnTypesToAccountForCovariance(array $childReturnTypes, array $parentReturnTypes) {
		foreach ($childReturnTypes as $key => $childReturnType) {
			if ($this->symbolTable->isDefinedClass($childReturnType)) {
				foreach ($parentReturnTypes as $parentReturnType) {
					if ($this->symbolTable->isDefinedClass($parentReturnType) && $this->symbolTable->isParentClassOrInterface($parentReturnType, $childReturnType)) {
						$childReturnTypes[$key] = $parentReturnType;
					}
				}
			}
		}

		return $childReturnTypes;
	}

	/**
	 * @param MethodInterface $method
	 *
	 * @return string[]
	 */
	private function getReturnTypesForMethod(MethodInterface $method) {
		$returnTypes = [];
		TypeComparer::forEachType($method->getComplexReturnType(), function ($type) use (&$returnTypes) {
			if ($type instanceof Node\Name) {
				$type = TypeComparer::identifierFromName($type->toString());
			}

			$returnTypes[] = $type;
		});
		$returnTypes = array_column($returnTypes, 'name');
		$returnTypes = !empty($returnTypes) ? $returnTypes : ["mixed"];

		return $returnTypes;
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
				if (!$current) {
					return null;
				}
			} else {
				return null;
			}
		}
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
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
				if (!$interface) {
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
		if (!$parentClass) {
			$this->emitError($fileName, $node->extends, ErrorConstants::TYPE_UNKNOWN_CLASS, "Unable to find parent " . $node->extends);
		} else if ($parentClass->isEnum()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ILLEGAL_ENUM, "Enums can not be extended");
		}

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
		if (!$node->isAbstract()) {
			foreach ($interface->getMethodNames() as $interfaceMethod) {
				$classMethod = $this->implementsMethod($node, $interfaceMethod);
				if (!$classMethod) {
					if (!$node->isAbstract()) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNIMPLEMENTED_METHOD, $node->name . " does not implement method " . $interfaceMethod);
					}
				} else {
					$this->checkMethod($fileName, $node, $classMethod, $interface->getMethod($interfaceMethod));
				}
			}
		}
	}
}
