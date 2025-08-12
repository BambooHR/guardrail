<?php

namespace BambooHR\Guardrail\Checks;

use Attribute;
use BambooHR\Guardrail\Abstractions\AttributeInterface;
use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\ConstantExpressionEvaluator;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node;
use PhpParser\Node\Attribute as NodeAttribute;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;

class AttributeCheck extends BaseCheck
{
	private InstantiationCheck $instantiationCheck;
	private ConstantExpressionEvaluator $constantExpressionEvaluator;

	public function __construct(SymbolTable $symbolTable, OutputInterface $doc)
	{
		parent::__construct($symbolTable, $doc);

		$this->instantiationCheck = new InstantiationCheck($symbolTable, $doc);
		$this->constantExpressionEvaluator = new ConstantExpressionEvaluator($symbolTable);
	}

	/**
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

	private function checkAttribute(string $fileName, NodeAttribute $attribute, Node $node, array &$seenAttributes, ?ClassLike $inside = null, ?Scope $scope = null): void
	{
		$attributeName = $attribute->name->toString();
		$attributeClass = $this->symbolTable->getAbstractedClass($attributeName);

		if (is_null($attributeClass)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Attribute class $attributeName does not exist");
			return;
		}

		$attributeAttribute = $this->findAttributeAttribute($attributeClass);
		if (is_null($attributeAttribute)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ATTRIBUTE_NOT_ATTRIBUTE, "Class $attributeName is not an attribute");
			return;
		}

		$this->validateAttributeArguments($fileName, $attribute, $inside, $scope);

		$flags = $this->getAttributeFlags($attributeAttribute, $inside, $scope);

		if (!is_null($flags)) {
			$this->validateAttributeTarget($node, $flags, $fileName, $attributeName);
			$this->validateAttributeRepeatability($fileName, $attribute, $flags, $seenAttributes);
		}
	}

	private function getAttributeFlags(AttributeInterface $attributeAttribute, ?ClassLike $inside, ?Scope $scope): ?int
	{
		$arguments = $attributeAttribute->getArguments();

		if (empty($arguments)) {
			return Attribute::TARGET_ALL;
		}

		if (count($arguments) != 1) {
			return null;
		}

		try {
			$argumentValue = $this->constantExpressionEvaluator->evaluate($arguments[0]->value);

			return is_int($argumentValue) ? $argumentValue : null;
		} catch (ConstExprEvaluationException) {
			return null;
		}
	}

	private function findAttributeAttribute(ClassInterface $class): ?AttributeInterface
	{
		foreach ($class->getAttributes() as $classAttribute) {
			if ($classAttribute->getClassName() === Attribute::class) {
				return $classAttribute;
			}
		}
		return null;
	}


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

	private function validateAttributeArguments(string $fileName, NodeAttribute $attribute, ?ClassLike $inside, ?Scope $scope): void
	{
		$new = new Node\Expr\New_(
			$attribute->name,
			$attribute->args
		);
		$this->instantiationCheck->run($fileName, $new, $inside, $scope);
	}

	public function validateAttributeTarget(Node $node, int $attributeAttributeFlags, string $fileName, string $attributeName): void
	{
		if (!($this->getNodeTargetFlag($node) & $attributeAttributeFlags)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ATTRIBUTE_WRONG_TARGET, "Attribute $attributeName cannot be applied to target " . $node->getType());
		}
	}

	private function validateAttributeRepeatability(string $fileName, NodeAttribute $attribute, int $attributeAttributeFlags, array &$seenAttributes): void
	{
		$attributeName = $attribute->name->toString();
		if (isset($seenAttributes[$attributeName]) && !($attributeAttributeFlags & Attribute::IS_REPEATABLE)) {
			$this->emitError($fileName, $attribute->name, ErrorConstants::TYPE_ATTRIBUTE_NOT_REPEATABLE, "Attribute " . $attributeName . " is not repeatable");
		}
		$seenAttributes[$attributeName] = true;
	}
}
