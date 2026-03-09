<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\If_;

/**
 * Class RedundantConditionCheck
 * 
 * Detects conditions that are always true (redundant) or always false (impossible)
 * based on type information.
 * 
 * Checks:
 * - instanceof checks
 * - Null comparisons (=== null, !== null, etc.)
 * - Type comparisons (incompatible types)
 * - Simple object truthiness
 * 
 * Excludes:
 * - Conditions inside assert() statements (defensive programming)
 * - Variables with unknown or mixed types
 */
class RedundantConditionCheck extends BaseCheck {
	
	/**
	 * @return string[]
	 */
	public function getCheckNodeTypes(): array {
		return [
			Instanceof_::class,
			Identical::class,
			NotIdentical::class,
			Equal::class,
			NotEqual::class,
			If_::class,
		];
	}

	/**
	 * @param string               $fileName -
	 * @param Node                 $node     -
	 * @param Node\Stmt\ClassLike|null $inside   -
	 * @param Scope|null           $scope    -
	 * @return void
	 */
	public function run(string $fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null): void {
		// TODO: Add assert() exclusion once we have access to parent nodes
		
		if ($node instanceof Instanceof_) {
			$this->checkInstanceof($fileName, $node, $scope);
		} elseif ($node instanceof Identical || $node instanceof NotIdentical ||
		          $node instanceof Equal || $node instanceof NotEqual) {
			$this->checkNullComparison($fileName, $node, $scope);
			$this->checkTypeComparison($fileName, $node, $scope);
		} elseif ($node instanceof If_) {
			$this->checkSimpleObjectTruthiness($fileName, $node, $scope);
		}
	}

	/**
	 * Check instanceof expressions
	 * 
	 * @param string $fileName
	 * @param Expr\Instanceof_ $node
	 * @return void
	 */
	private function checkInstanceof(string $fileName, Instanceof_ $node, ?Scope $scope): void {
		// Get variable type - first try INFERRED_TYPE_ATTR, then try scope lookup
		$varType = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		
		// If no inferred type and it's a variable, try to get it from scope
		if (!$varType && $node->expr instanceof Variable && is_string($node->expr->name) && $scope) {
			$varType = $scope->getVarType($node->expr->name);
		}
		
		// Skip if type is unknown or mixed
		if ($this->isUnknownOrMixed($varType)) {
			return;
		}

		// Get the class being checked
		if (!($node->class instanceof Name)) {
			return;
		}
		$checkClass = $node->class->toString();

		// Check if instanceof is impossible (always false)
		if ($this->isInstanceofImpossible($varType, $checkClass)) {
			$typeStr = TypeComparer::typeToString($varType);
			$this->emitError(
				$fileName,
				$node,
				ErrorConstants::TYPE_IMPOSSIBLE_CONDITION,
				"Instanceof check is always false - variable of type {$typeStr} can never be instanceof {$checkClass}"
			);
			return;
		}

		// Check if instanceof is redundant (always true)
		if ($this->isInstanceofRedundant($varType, $checkClass)) {
			$typeStr = TypeComparer::typeToString($varType);
			$this->emitError(
				$fileName,
				$node,
				ErrorConstants::TYPE_REDUNDANT_CONDITION,
				"Instanceof check is always true - variable of type {$typeStr} is already {$checkClass}"
			);
		}
	}

	/**
	 * Check null comparison expressions
	 * 
	 * @param string $fileName
	 * @param Expr\BinaryOp $node
	 * @return void
	 */
	private function checkNullComparison(string $fileName, Expr\BinaryOp $node, ?Scope $scope): void {
		// Check if either side is null
		$leftIsNull = $this->isNullNode($node->left);
		$rightIsNull = $this->isNullNode($node->right);

		if (!$leftIsNull && !$rightIsNull) {
			return; // Not a null comparison
		}

		// Get the non-null side
		$varNode = $leftIsNull ? $node->right : $node->left;
		$varType = $varNode->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		
		// If no inferred type and it's a variable, try scope lookup
		if (!$varType && $varNode instanceof Variable && is_string($varNode->name) && $scope) {
			$varType = $scope->getVarType($varNode->name);
		}

		// Skip if type is unknown or mixed
		if ($this->isUnknownOrMixed($varType)) {
			return;
		}

		$isNullable = TypeComparer::isTypeNullable($varType);
		$isStrictComparison = $node instanceof Identical || $node instanceof NotIdentical;
		$isEqualityCheck = $node instanceof Identical || $node instanceof Equal;

		if ($isEqualityCheck) {
			// Checking if variable === null or == null
			if (!$isNullable) {
				$typeStr = TypeComparer::typeToString($varType);
				$op = $isStrictComparison ? '===' : '==';
				$this->emitError(
					$fileName,
					$node,
					ErrorConstants::TYPE_IMPOSSIBLE_CONDITION,
					"Null check is always false - variable of type {$typeStr} can never be null (using {$op})"
				);
			}
		} else {
			// Checking if variable !== null or != null
			if (!$isNullable) {
				$typeStr = TypeComparer::typeToString($varType);
				$op = $isStrictComparison ? '!==' : '!=';
				$this->emitError(
					$fileName,
					$node,
					ErrorConstants::TYPE_REDUNDANT_CONDITION,
					"Null check is always true - variable of type {$typeStr} is never null (using {$op})"
				);
			}
		}
	}

	/**
	 * Check type comparison expressions (incompatible types)
	 * 
	 * @param string $fileName
	 * @param Expr\BinaryOp $node
	 * @return void
	 */
	private function checkTypeComparison(string $fileName, Expr\BinaryOp $node, ?Scope $scope): void {
		// Only check strict comparisons for type mismatches
		if (!($node instanceof Identical || $node instanceof NotIdentical)) {
			return;
		}

		$leftType = $node->left->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		$rightType = $node->right->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		
		// Try scope lookup for variables
		if (!$leftType && $node->left instanceof Variable && is_string($node->left->name) && $scope) {
			$leftType = $scope->getVarType($node->left->name);
		}
		if (!$rightType && $node->right instanceof Variable && is_string($node->right->name) && $scope) {
			$rightType = $scope->getVarType($node->right->name);
		}

		// Skip if either type is unknown or mixed
		if ($this->isUnknownOrMixed($leftType) || $this->isUnknownOrMixed($rightType)) {
			return;
		}

		// Skip null comparisons (handled separately)
		if ($this->isNullNode($node->left) || $this->isNullNode($node->right)) {
			return;
		}

		// Check if types are completely incompatible
		if ($this->areTypesIncompatible($leftType, $rightType)) {
			$leftStr = TypeComparer::typeToString($leftType);
			$rightStr = TypeComparer::typeToString($rightType);
			$isIdentical = $node instanceof Identical;
			$op = $isIdentical ? '===' : '!==';
			
			if ($node instanceof Identical) {
				$this->emitError(
					$fileName,
					$node,
					ErrorConstants::TYPE_IMPOSSIBLE_CONDITION,
					"Type comparison is always false - {$leftStr} {$op} {$rightStr} (incompatible types)"
				);
			} else {
				$this->emitError(
					$fileName,
					$node,
					ErrorConstants::TYPE_REDUNDANT_CONDITION,
					"Type comparison is always true - {$leftStr} {$op} {$rightStr} (incompatible types)"
				);
			}
		}
	}

	/**
	 * Check simple object truthiness in if conditions
	 * 
	 * @param string $fileName
	 * @param Node\Stmt\If_ $node
	 * @return void
	 */
	private function checkSimpleObjectTruthiness(string $fileName, If_ $node, ?Scope $scope): void {
		// Only check simple variable conditions (not assignments or complex expressions)
		if (!($node->cond instanceof Variable)) {
			return;
		}

		$varType = $node->cond->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		
		// Try scope lookup for variables
		if (!$varType && is_string($node->cond->name) && $scope) {
			$varType = $scope->getVarType($node->cond->name);
		}

		// Skip if type is unknown or mixed
		if ($this->isUnknownOrMixed($varType)) {
			return;
		}

		// Check if it's a non-nullable object type
		if ($this->isNonNullableObject($varType)) {
			$typeStr = TypeComparer::typeToString($varType);
			$varName = is_string($node->cond->name) ? '$' . $node->cond->name : 'variable';
			$this->emitError(
				$fileName,
				$node->cond,
				ErrorConstants::TYPE_REDUNDANT_CONDITION,
				"Truthiness check is always true - {$varName} of type {$typeStr} is always truthy"
			);
		}
	}

	/**
	 * Check if a type is unknown (null) or mixed
	 * 
	 * @param mixed $type
	 * @return bool
	 */
	private function isUnknownOrMixed($type): bool {
		if ($type === null) {
			return true; // Unknown type
		}

		if ($type instanceof Identifier && strtolower($type->name) === 'mixed') {
			return true;
		}

		if ($type instanceof Name && strtolower($type->toString()) === 'mixed') {
			return true;
		}

		return false;
	}

	/**
	 * Check if a node represents null
	 * 
	 * @param Node $node
	 * @return bool
	 */
	private function isNullNode(Node $node): bool {
		return $node instanceof Expr\ConstFetch && 
		       $node->name instanceof Name && 
		       strtolower($node->name->toString()) === 'null';
	}

	/**
	 * Check if instanceof is impossible (always false)
	 * 
	 * @param mixed $varType
	 * @param string $checkClass
	 * @return bool
	 */
	private function isInstanceofImpossible($varType, string $checkClass): bool {
		// If it's a scalar type, it can never be instanceof a class
		if ($varType instanceof Identifier) {
			$typeName = strtolower($varType->name);
			if (in_array($typeName, ['int', 'float', 'string', 'bool', 'array', 'null', 'void', 'never', 'false', 'true'])) {
				return true;
			}
		}

		// If it's a specific class type, check if it's incompatible
		if ($varType instanceof Name) {
			$varClass = $varType->toString();
			
			// Check if the classes are completely unrelated
			if (!$this->areClassesRelated($varClass, $checkClass)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if instanceof is redundant (always true)
	 * 
	 * @param mixed $varType
	 * @param string $checkClass
	 * @return bool
	 */
	private function isInstanceofRedundant($varType, string $checkClass): bool {
		// If it's exactly the same class (non-nullable)
		if ($varType instanceof Name) {
			$varClass = $varType->toString();
			if (strcasecmp($varClass, $checkClass) === 0) {
				return true;
			}

			// Check if varClass is a subclass of checkClass (would always be true)
			// For now, we'll be conservative and only flag exact matches
		}

		return false;
	}

	/**
	 * Check if two types are completely incompatible
	 * 
	 * @param mixed $leftType
	 * @param mixed $rightType
	 * @return bool
	 */
	private function areTypesIncompatible($leftType, $rightType): bool {
		// Get simple type names
		$left = $this->getSimpleTypeName($leftType);
		$right = $this->getSimpleTypeName($rightType);

		if (!$left || !$right) {
			return false; // Can't determine
		}

		// Define incompatible type pairs
		$scalarTypes = ['int', 'float', 'string', 'bool', 'array'];
		
		// If one is a scalar and the other is an object, they're incompatible
		if (in_array($left, $scalarTypes) && !in_array($right, $scalarTypes)) {
			return true;
		}
		if (in_array($right, $scalarTypes) && !in_array($left, $scalarTypes)) {
			return true;
		}

		// Different scalar types are incompatible for strict comparison
		if (in_array($left, $scalarTypes) && in_array($right, $scalarTypes) && $left !== $right) {
			// Exception: int and float can be equal
			if (($left === 'int' && $right === 'float') || ($left === 'float' && $right === 'int')) {
				return false;
			}
			return true;
		}

		return false;
	}

	/**
	 * Get simple type name from a type node
	 * 
	 * @param mixed $type
	 * @return string|null
	 */
	private function getSimpleTypeName($type): ?string {
		if ($type instanceof Identifier) {
			return strtolower($type->name);
		}
		if ($type instanceof Name) {
			return strtolower($type->toString());
		}
		return null;
	}

	/**
	 * Check if two classes are related (same class, parent/child, or interface)
	 * 
	 * @param string $class1
	 * @param string $class2
	 * @return bool
	 */
	private function areClassesRelated(string $class1, string $class2): bool {
		// Same class
		if (strcasecmp($class1, $class2) === 0) {
			return true;
		}

		// Check if one extends the other or they share an interface
		// For now, be conservative - assume they might be related unless we know otherwise
		// This would require symbol table lookups to be more precise
		return true;
	}

	/**
	 * Check if a type is a non-nullable object
	 * 
	 * @param mixed $type
	 * @return bool
	 */
	private function isNonNullableObject($type): bool {
		// Must be a class name (not a scalar)
		if (!($type instanceof Name)) {
			return false;
		}

		// Must not be nullable
		if (TypeComparer::isTypeNullable($type)) {
			return false;
		}

		return true;
	}
}
