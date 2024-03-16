<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractedClass_;
use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PHPUnit\Util\Type;

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

	private TypeComparer $typeComparer;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeComparer = new TypeComparer(($symbolTable));
	}

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
	protected function checkMethod($fileName, Class_ $class, Node\FunctionLike $astNode, MethodInterface $method, MethodInterface $parentMethod) {

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
					if ($oldVisibility !== 'private') {
						$isContravariant = $this->typeComparer->isContravariant($parentParam->getType(), $childParam->getType());
						if (!$isContravariant) {
							$childParamType = TypeComparer::typeToString($childParam->getType());
							$parentParamType = TypeComparer::typeToString($parentParam->getType());
							$this->emitErrorOnLine($fileName, $method->getStartingLine(), self::TYPE_SIGNATURE_TYPE, "Child method parameter " . $childParam->getName() . " type mismatch " . $className . "::" . $method->getName() . " : $parentParamType -> $childParamType");
						}
					}
					if ($childParam->getName() != $parentParam->getName()) {
						$this->emitError(
							$fileName,
							$astNode,
							ErrorConstants::TYPE_SIGNATURE_NAME,
							"Child method renames parameter " . $className . "::" . $method->getName() . " \$" . $parentParam->getName() . " becomes \$" . $childParam->getName()
						);
					}
					if ($childParam->isReference() != $parentParam->isReference()) {
						$this->emitError(
							$fileName,
							$astNode,
							self::TYPE_SIGNATURE_TYPE,
							"Child method " . $className . "::" . $method->getName() . " add or removes & in \$" . $childParam->getName()
						);
					}
					if (!$childParam->isOptional() && $parentParam->isOptional()) {
						$this->emitError(
							$fileName,
							$astNode,
							self::TYPE_SIGNATURE_TYPE,
							"Child method " . $className . "::" . $method->getName() . " changes parameter \$" . $childParam->getName() . " to be required."
						);
					}
				} else {
					if (!$childParam->isOptional()) {
						$this->emitError(
							$fileName,
							$astNode,
							self::TYPE_SIGNATURE_TYPE,
							"Child method " . $method->getName() . " adds parameter \$" . $childParam->getName() . " that doesn't have a default value"
						);
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
		$parentType = $parentMethod->getComplexReturnType();
		$childType = $childMethod->getComplexReturnType();
		$isCovariant = $this->typeComparer->isCovariant( $parentType, $childType );
		if (!$isCovariant) {
			$this->emitErrorOnLine($fileName, $childMethod->getStartingLine(), self::TYPE_SIGNATURE_RETURN,
				"Child method return types do not match parent return types " . $className . "::" .
				$childMethod->getName() . " : " . TypeComparer::typeToString($parentMethod->getComplexReturnType())
					." to ". TypeComparer::typeToString($childMethod->getComplexReturnType())
			);
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

		foreach($node->stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name!="__construct") {
				$method = Util::findAbstractedMethod($node->extends, $stmt->name, $this->symbolTable);
				if ($method) {
					$this->checkMethod($fileName, $node, $stmt, $class->getMethod($stmt->name), $method);
				}
			}
		}
	}

	private function processNodeImplementsNotAbstract($fileName, Class_ $node, ClassInterface $interface) {
		// Don't force abstract classes to implement all methods.
		if (!$node->isAbstract()) {
			foreach ($interface->getMethodNames() as $interfaceMethod) {
				$classMethod = $this->implementsMethod($node, $interfaceMethod);
				if (!$classMethod) {
					if (!$node->isAbstract()) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNIMPLEMENTED_METHOD, $node->name . " does not implement method " . $interfaceMethod);
					}
				} else {
					foreach($node->stmts as $stmt) {
						if ($stmt instanceof Node\Stmt\ClassMethod && strcasecmp($stmt->name, $interfaceMethod)==0) {
							$this->checkMethod($fileName, $node, $stmt, $classMethod, $interface->getMethod($interfaceMethod));
							break;
						}
					}
				}
			}
		}
	}
}

