<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\Grabber;
use BambooHR\Guardrail\TypeComparer;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * Class ClassAbstraction
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class ClassAbstraction implements ClassInterface {

	/**
	 * @var ClassLike
	 */
	private $class;

	/**
	 * ClassAbstraction constructor.
	 *
	 * @param ClassLike $class Instance of ClassLike
	 */
	public function __construct(ClassLike $class) {
		$this->class = $class;
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		$class = $this->class;
		return isset($class->namespacedName) ? strval($class->namespacedName) : "";
	}

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract() {
		$class = $this->class;
		if ($class instanceof Class_) {
			return $class->isAbstract();
		} else {
			return false;
		}
	}

	public function isReadOnly(): bool {
		return ($this->class instanceof Class_ ? $this->class->isReadonly() : false);
	}

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames() {
		$ret = [];
		foreach ($this->class->getMethods() as $method) {
			$ret[] = $method->name;
		}
		return $ret;
	}

	/**
	 * getParentClassName
	 *
	 * @return string
	 */
	public function getParentClassName() {
		$class = $this->class;
		if ($class instanceof \PhpParser\Node\Stmt\Class_) {
			return strval($class->extends);
		} else {
			return "";
		}
	}

	/**
	 * isInterface
	 *
	 * @return bool
	 */
	public function isInterface() {
		return $this->class instanceof \PhpParser\Node\Stmt\Interface_;
	}

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames() {
		$ret = [];
		$class = $this->class;
		if ($class instanceof Interface_) {
			foreach ($class->extends as $extend) {
				$ret[] = strval($extend);
			}
		} else {
			/** @var Class_ $class */
			foreach ($class->implements as $implement) {
				$ret[] = strval($implement);
			}
		}
		return $ret;
	}

	/**
	 * getMethod
	 *
	 * @param string $name Instance of ClassMethod
	 *
	 * @return ClassMethod|null
	 */
	public function getMethod($name) {
		$method = $this->class->getMethod($name);
		return $method ? new ClassMethod($this,$method) : null;
	}

	/**
	 * hasConstant
	 *
	 * @param string $name Property name
	 *
	 * @return bool
	 */
	public function hasConstant($name):bool {
		return $this->getConstantExpr($name) ? true : false;
	}

	public function getConstantExpr($name):null|Expr|Name|Identifier {

		if ($this->isEnum()) {
			$constants = Grabber::filterByType($this->class->stmts, EnumCase::class);
			foreach($constants as $enumOption) {
				/** @var EnumCase $enumOption */
				if (strcasecmp($enumOption->name,$name)==0) {
					return $this->class->namespacedName;
				}
			}
		}
		$constants = Grabber::filterByType($this->class->stmts, [ClassConst::class, EnumCase::class]);
		foreach ($constants as $constList) {
			if ($constList instanceof ClassConst) {
				foreach ($constList->consts as $const) {
					if (strcasecmp($const->name, $name) == 0) {
						if($const->value instanceof LNumber) {
							return TypeComparer::identifierFromName("int");
						} else if ($const->value instanceof String_) {
							return TypeComparer::identifierFromName("string");
						} else if (
							$const->value instanceof Expr\ConstFetch &&
							(
								strcasecmp($const->value->name,"true")==0 ||
								strcasecmp($const->value->name,"false")==0
							)
						) {
							return TypeComparer::identifierFromName("bool");
						}
						return $const->value;
					}
				}
			} else {
				if($constList instanceof EnumCase) {
					if (strcasecmp($constList->name, $name)==0) {
						return $this->class->namespacedName;
					}
				}
			}
		}
		return null;
	}

	/**
	 * getPropertyNames
	 *
	 * @return array
	 */
	public function getPropertyNames() {
		$ret = [];
		$properties = Grabber::filterByType($this->class->stmts, \PhpParser\Node\Stmt\Property::class);
		foreach ($properties as $prop) {
			/** @var \PhpParser\Node\Stmt\Property $prop */
			foreach ($prop->props as $propertyProperty) {
				/** @var PropertyProperty $propertyProperty */
				$ret[] = $propertyProperty->name;
			}
		}
		return $ret;
	}

	/**
	 * getProperty
	 *
	 * @param string $name The name of the property
	 *
	 * @return Property
	 */
	public function getProperty($name) {
		foreach ($this->class->stmts as $prop) {
			if ($prop instanceof \PhpParser\Node\Stmt\Property) {
				/** @var \PhpParser\Node\Stmt\Property $prop */
				foreach ($prop->props as $propertyProperty) {
					/** @var PropertyProperty $propertyProperty */
					if ($propertyProperty->name == $name) {
						if ($prop->isPrivate()) {
							$access = "private";
						} else {
							if ($prop->isProtected()) {
								$access = "protected";
							} else {
								$access = "public";
							}
						}
						$type = $prop->type;
						//if (Config::shouldUseDocBlockForProperties() && empty($type)) {
						//	$type = Scope::nameFromName($propertyProperty->namespacedType);
						//}
						return new Property($this,$propertyProperty->name, $type, $access, $prop->isStatic(), $prop->isReadOnly());
					}
				}
			}
		}
		return null;
	}

	public function isEnum(): bool {
		return $this->class instanceof Enum_;
	}

	public function getAttributes(): array
	{
		$attributes = [];
		foreach ($this->class->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				$attributes[] = new AttributeAbstraction($attr);
			}
		}
		return $attributes;
	}

	public function getConstantValueExpression(string $name): ?Expr
	{
		foreach ($this->class->getConstants() as $classConsts) {
			foreach ($classConsts->consts as $const) {
				if ($const->name == $name) {
					return $const->value;
				}
			}
		}

		return null;
	}
}
