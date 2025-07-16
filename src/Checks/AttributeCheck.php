<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Attribute as NodeAttribute;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Enum_;
use Attribute;

class AttributeCheck extends BaseCheck
{
	private InstantiationCheck $instantiationCheck;

	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);

		$this->instantiationCheck = new InstantiationCheck($symbolTable, $doc);
	}

	/**
     * getCheckNodeTypes
     *
     * @return string[]
     */
    public function getCheckNodeTypes(): array
	{
        return [
            Function_::class,
            Class_::class,
            ClassConst::class,
            Property::class,
            ClassMethod::class,
            Param::class,
            Interface_::class,
            Trait_::class,
            Enum_::class
        ];
    }

    /**
     * @param string         $fileName The name of the file we are parsing
     * @param Node           $node     Instance of the Node
     * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
     * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
     *
     * @return void
     */
    public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null): void
	{
		$seenAttributes = [];
		/** @noinspection PhpPossiblePolymorphicInvocationInspection */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attribute) {
				$this->checkAttribute($fileName, $attribute, $node, $seenAttributes, $inside, $scope);
			}
		}
    }

	/**
	 * @param string $fileName
	 * @param NodeAttribute $attribute
	 * @param Node $node
	 * @param array $seenAttributes
	 * @param ClassLike|null $inside
	 * @param Scope|null $scope
	 * @return void
	 */
    private function checkAttribute(string $fileName, NodeAttribute $attribute, Node $node, array &$seenAttributes, ?ClassLike $inside = null, ?Scope $scope = null): void
    {
		$attributeClass = $this->verifyAttributeClass($fileName, $attribute);
		if ($attributeClass === null) {
			return;
		}

		$this->verifyAttributeRepeatability($fileName, $attribute, $attributeClass, $seenAttributes);
		$this->verifyAttributeTarget($fileName, $attribute, $attributeClass, $node);
		$this->runInstantiationCheck($fileName, $attribute, $inside, $scope);
    }

	private function verifyAttributeClass(string $fileName, NodeAttribute $attribute): ?Class_
	{
		$attributeName = $attribute->name->toString();
		$attributeClass = $this->symbolTable->getClass($attributeName);

		if ($attributeClass === null) {
			$this->emitError($fileName, $attribute->name, ErrorConstants::TYPE_UNKNOWN_CLASS, "Attribute class $attributeName does not exist");
			return null;
		}

		if (!$this->isAttributeClass($attributeClass)) {
			$this->emitError($fileName, $attribute->name, ErrorConstants::TYPE_ATTRIBUTE_NOT_ATTRIBUTE, "Class $attributeName is not an attribute");
			return null;
		}
		return $attributeClass;
	}

	private function verifyAttributeRepeatability(string $fileName, NodeAttribute $attribute, Class_ $attributeClass, array &$seenAttributes): void
	{
		$attributeName = $attribute->name->toString();
		if (isset($seenAttributes[$attributeName]) && !$this->isAttributeRepeatable($attributeClass)) {
			$this->emitError($fileName, $attribute->name, ErrorConstants::TYPE_ATTRIBUTE_NOT_REPEATABLE, "Attribute " . $attributeName . " is not repeatable");
		}
		$seenAttributes[$attributeName] = true;
	}

	private function verifyAttributeTarget(string $fileName, NodeAttribute $attribute, Class_ $attributeClass, Node $node): void
	{
		if (!$this->hasValidTarget($attributeClass, $node)) {
			$this->emitError($fileName, $attribute->name, ErrorConstants::TYPE_ATTRIBUTE_WRONG_TARGET, "Attribute " . $attribute->name->toString() . " cannot be used on this type of declaration");
		}
	}

	private function runInstantiationCheck(string $fileName, NodeAttribute $attribute, ?ClassLike $inside, ?Scope $scope): void
	{
		$new = new Node\Expr\New_(
			$attribute->name,
			$attribute->args
		);
		$this->instantiationCheck->run($fileName, $new, $inside, $scope);
	}

    /**
     * @param Class_ $class
     * @return bool
     */
    private function isAttributeClass(Class_ $class): bool
    {
		return Util::getPhpAttribute(Attribute::class, $class->attrGroups) !== null;
    }

    /**
     * @param Class_ $attributeClass
     * @param Node $node
     * @return bool
     */
    private function hasValidTarget(Class_ $attributeClass, Node $node): bool
    {
        $supportedTargets = $this->getAttributeTargets($attributeClass);
        $nodeTarget = $this->getNodeTargetFlag($node);

        return ($supportedTargets & $nodeTarget) !== 0;
    }

    /**
     * @param Class_ $attributeClass
     * @return int
     */
    private function getAttributeTargets(Class_ $attributeClass): int
    {
        return $this->getAttributeFlags($attributeClass) & Attribute::TARGET_ALL;
    }

    /**
     * @param Class_ $attributeClass
     * @return int
     */
    private function getAttributeFlags(Class_ $attributeClass): int
    {
        if ($attributeClass->name->toString() == Attribute::class) {
            return Attribute::TARGET_CLASS;
        }

        $attributeAttribute = Util::getPhpAttribute(Attribute::class, $attributeClass->attrGroups);

        if ($attributeAttribute && !empty($attributeAttribute->args)) {
            $arg = $attributeAttribute->args[0]->value;
            return $this->evaluateAttributeFlags($arg);
        }

        return Attribute::TARGET_ALL;
    }

    /**
     * @param Class_ $attributeClass
     * @return bool
     */
    private function isAttributeRepeatable(Class_ $attributeClass): bool
    {
        return ($this->getAttributeFlags($attributeClass) & Attribute::IS_REPEATABLE) !== 0;
    }

    /**
     * @param Node\Expr $expr
     * @return int
     */
    private function evaluateAttributeFlags(Node\Expr $expr): int
    {
        if ($expr instanceof Node\Expr\BinaryOp\BitwiseOr) {
            return $this->evaluateAttributeFlags($expr->left) | $this->evaluateAttributeFlags($expr->right);
        }

        if ($expr instanceof Node\Expr\ClassConstFetch) {
            if ($expr->class->toString() === 'Attribute' || $expr->class->toString() === Attribute::class) {
				return match ($expr->name->toString()) {
					'TARGET_CLASS' => Attribute::TARGET_CLASS,
					'TARGET_FUNCTION' => Attribute::TARGET_FUNCTION,
					'TARGET_METHOD' => Attribute::TARGET_METHOD,
					'TARGET_PROPERTY' => Attribute::TARGET_PROPERTY,
					'TARGET_CLASS_CONSTANT' => Attribute::TARGET_CLASS_CONSTANT,
					'TARGET_PARAMETER' => Attribute::TARGET_PARAMETER,
					'TARGET_ALL' => Attribute::TARGET_ALL,
					'IS_REPEATABLE' => Attribute::IS_REPEATABLE,
					default => 0,
				};
            }
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        }

        // Unknown or unsupported expression type, return 0 to cause a validation failure.
        return 0;
    }

    /**
     * @param Node $node
     * @return int
     */
    private function getNodeTargetFlag(Node $node): int
    {
        return match (get_class($node)) {
            Class_::class, Interface_::class, Trait_::class, Enum_::class => Attribute::TARGET_CLASS,
            Function_::class => Attribute::TARGET_FUNCTION,
            ClassMethod::class => Attribute::TARGET_METHOD,
            Property::class => Attribute::TARGET_PROPERTY,
            ClassConst::class => Attribute::TARGET_CLASS_CONSTANT,
            Param::class => Attribute::TARGET_PARAMETER,
            default => 0,
        };
    }
}