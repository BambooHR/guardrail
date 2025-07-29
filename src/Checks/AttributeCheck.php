<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\AttributeInterface;
use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use Error;
use InvalidArgumentException;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Attribute as NodeAttribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
	private ConstantExpressionEvaluator $constantExpressionEvaluator;

	public function __construct(SymbolTable $symbolTable, OutputInterface $doc)
	{
		parent::__construct($symbolTable, $doc);

		$this->instantiationCheck = new InstantiationCheck($symbolTable, $doc);
		$this->constantExpressionEvaluator = new ConstantExpressionEvaluator($symbolTable);
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
	 * @param string $fileName The name of the file we are parsing
	 * @param Node $node Instance of the Node
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null $scope Instance of the Scope (all variables in the current state) [optional]
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
		$argumentExpressions = $attributeAttribute->getArgumentExpressions();

		if (empty($argumentExpressions)) {
			return Attribute::TARGET_ALL;
		}

		if (count($argumentExpressions) != 1) {
			return null;
		}

		try {
			$argumentValue = $this->constantExpressionEvaluator->evaluate($argumentExpressions[0], $inside, $scope);

			return is_int($argumentValue) ? $argumentValue : null;
		} catch (ConstExprEvaluationException) {
			return null;
		}
	}

	private function findAttributeAttribute(ClassInterface $class): ?AttributeInterface
	{
		foreach ($class->getAttributes() as $classAttribute) {
			if ($classAttribute->getName() === Attribute::class) {
				return $classAttribute;
			}
		}
		return null;
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

	private function validateAttributeArguments(string $fileName, NodeAttribute $attribute, ?ClassLike $inside, ?Scope $scope): void
	{
		$new = new Node\Expr\New_(
			$attribute->name,
			$attribute->args
		);
		$this->instantiationCheck->run($fileName, $new, $inside, $scope);
	}

	/**
	 * @param Node $node
	 * @param int $attributeAttributeFlags
	 * @param string $fileName
	 * @param string $attributeName
	 * @return void
	 */
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

readonly class ConstantExpressionEvaluator {
	public function __construct(private SymbolTable $symbolTable) {}
	/**
	 * @throws ConstExprEvaluationException
	 */
	public function evaluate(Expr $expr, ?ClassLike $inside, ?Scope $scope) {
		$evaluator = new ConstExprEvaluator(
			fn($unhandledExpr) => $this->fallbackEvaluator($unhandledExpr, $inside, $scope),
		);

		return $evaluator->evaluateSilently($expr);
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function fallbackEvaluator(Expr $expr, ?ClassLike $inside, ?Scope $scope) {
		// TODO(shayman@bamboohr.com): 1. Handle infinite recursion of constant references
		// 2. Implement or remove cases
		if ($expr instanceof Node\Scalar\MagicConst) {
			throw new ConstExprEvaluationException();
		} else if ($expr instanceof Expr\ConstFetch) {
			throw new ConstExprEvaluationException();
		} else if ($expr instanceof Expr\ClassConstFetch) {
			return $this->evaluateClassConstFetch($expr, $inside, $scope);
		} else if ($expr instanceof New_) {
			throw new ConstExprEvaluationException();
		} else if ($expr instanceof PropertyFetch) {
			throw new ConstExprEvaluationException();
		}

		throw new ConstExprEvaluationException();
	}

	/**
	 * @throws ConstExprEvaluationException
	 * @throws InvalidArgumentException
	 */
	private function evaluateClassConstFetch(Expr $expr, ?ClassLike $inside, ?Scope $scope) {
		$className = $this->resolveClassName($expr->class, $inside, $scope);
		$name = $this->resolveName($expr->name, $inside, $scope);
		return $this->resolveValue($className, $name);
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function resolveClassName(Expr|Name $class, ?ClassLike $inside, ?Scope $scope): string {
		if ($class instanceof Name) {
			return match ($class->toString()) {
				"self", "static" => $inside->name->toString(),
				"parent" => $inside->extends->toString(),
				default => $class->toString(),
			};
		} else {
			return $this->evaluate($class->expr, $inside, $scope);
		}
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function resolveName(Expr|Error|Identifier $name, ?ClassLike $inside, ?Scope $scope) {
		if ($name instanceof Identifier) {
			return $name->toString();
		} else {
			return $this->evaluate($name, $inside, $scope);
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function resolveValue(string $class, string $name) {
		if ($name == "class") {
			return $class;
		}

		/** @var ClassInterface $classInterface */
		$classInterface = $this->symbolTable->getAbstractedClass($class);

		return $classInterface->getConstant($name);
	}
}
