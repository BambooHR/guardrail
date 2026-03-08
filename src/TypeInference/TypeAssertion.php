<?php

namespace BambooHR\Guardrail\TypeInference;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

/**
 * Class TypeAssertion
 * 
 * Handles type narrowing based on conditional expressions.
 * Analyzes conditions and updates variable types in scopes accordingly.
 * 
 * @package BambooHR\Guardrail\TypeInference
 */
class TypeAssertion {
	
	/**
	 * Apply type narrowing based on a condition
	 * 
	 * @param Node $condition The condition being evaluated
	 * @param Scope $scope The scope to modify
	 * @param bool $truthyBranch Whether this is the truthy or falsy branch
	 * @return void
	 */
	public static function narrowTypes(Node $condition, Scope $scope, bool $truthyBranch): void {
		// instanceof Foo -> $var is Foo in truthy branch
		if ($condition instanceof Node\Expr\Instanceof_) {
			self::handleInstanceOf($condition, $scope, $truthyBranch);
			return;
		}
		
		// if ($var) -> $var is non-null in truthy branch
		if ($condition instanceof Node\Expr\Variable) {
			self::handleTruthyCheck($condition, $scope, $truthyBranch);
			return;
		}
		
		// !$condition -> invert the narrowing
		if ($condition instanceof Node\Expr\BooleanNot) {
			self::narrowTypes($condition->expr, $scope, !$truthyBranch);
			return;
		}
		
		// isset() language construct
		if ($condition instanceof Node\Expr\Isset_) {
			self::handleIsset($condition, $scope, $truthyBranch);
			return;
		}
		
		// empty() language construct
		if ($condition instanceof Node\Expr\Empty_) {
			self::handleEmpty($condition, $scope, $truthyBranch);
			return;
		}
		
		// Function calls: is_null(), is_string(), etc.
		if ($condition instanceof Node\Expr\FuncCall) {
			self::handleTypeCheckFunction($condition, $scope, $truthyBranch);
			return;
		}
		
		// $a !== null, $a != null
		if ($condition instanceof Node\Expr\BinaryOp\NotIdentical ||
		    $condition instanceof Node\Expr\BinaryOp\NotEqual) {
			self::handleNotEqualNull($condition, $scope, $truthyBranch);
			return;
		}
		
		// $a === null, $a == null
		if ($condition instanceof Node\Expr\BinaryOp\Identical ||
		    $condition instanceof Node\Expr\BinaryOp\Equal) {
			self::handleEqualNull($condition, $scope, $truthyBranch);
			return;
		}
		
		// Boolean AND - both conditions must be true
		if ($condition instanceof Node\Expr\BinaryOp\BooleanAnd) {
			if ($truthyBranch) {
				// Both sides must be true
				self::narrowTypes($condition->left, $scope, true);
				self::narrowTypes($condition->right, $scope, true);
			}
			// In falsy branch, at least one is false - can't narrow
			return;
		}
		
		// Boolean OR - at least one condition must be true
		if ($condition instanceof Node\Expr\BinaryOp\BooleanOr) {
			if (!$truthyBranch) {
				// Both sides must be false
				self::narrowTypes($condition->left, $scope, false);
				self::narrowTypes($condition->right, $scope, false);
			}
			// In truthy branch, at least one is true - can't narrow
			return;
		}
	}
	
	/**
	 * Handle instanceof checks
	 * 
	 * @param Node\Expr\Instanceof_ $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleInstanceOf(Node\Expr\Instanceof_ $node, Scope $scope, bool $truthyBranch): void {
		if (!($node->expr instanceof Node\Expr\Variable) || 
		    !is_string($node->expr->name) ||
		    !($node->class instanceof Node\Name)) {
			return;
		}
		
		$varName = $node->expr->name;
		$className = $node->class;
		
		if ($truthyBranch) {
			// In truthy branch, variable IS this class
			$scope->setVarType($varName, $className, $node->getLine());
			
			// instanceof proves the variable is not null and is set
			$var = $scope->getVarObject($varName);
			if ($var) {
				$var->mayBeNull = false;
				$var->mayBeUnset = false;
				// Remove null from type
				$var->type = self::removeNull($var->type);
			}
		}
		// In falsy branch, we know it's NOT this class, but could still be other types
		// For now, we don't narrow in the falsy branch
	}
	
	/**
	 * Handle truthiness checks (if ($var))
	 * 
	 * @param Node\Expr\Variable $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleTruthyCheck(Node\Expr\Variable $node, Scope $scope, bool $truthyBranch): void {
		if (!is_string($node->name)) {
			return;
		}
		
		$varName = $node->name;
		$var = $scope->getVarObject($varName);
		
		if (!$var) {
			return;
		}
		
		if ($truthyBranch) {
			// In truthy branch, variable is not null and not unset
			$var->mayBeNull = false;
			// Remove null from type
			$var->type = self::removeNull($var->type);
			$var->mayBeUnset = false;
		} else {
			// In falsy branch, variable could be null, false, 0, "", []
			// We know it's set (otherwise we couldn't check it), but it could be null
			$var->mayBeUnset = false;
			// Don't set mayBeNull = true, as it could be other falsy values
		}
	}
	
	/**
	 * Handle isset() language construct
	 * 
	 * @param Node\Expr\Isset_ $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleIsset(Node\Expr\Isset_ $node, Scope $scope, bool $truthyBranch): void {
		// isset() can check multiple variables: isset($a, $b, $c)
		foreach ($node->vars as $varNode) {
			if (!($varNode instanceof Node\Expr\Variable) || !is_string($varNode->name)) {
				continue;
			}
			
			$varName = $varNode->name;
			$var = $scope->getVarObject($varName);
			
			if ($truthyBranch) {
				// Variable is set and not null
				if (!$var) {
					// Variable doesn't exist yet - create it with mixed type
					$scope->setVarType($varName, TypeComparer::identifierFromName('mixed'), $node->getLine());
				}
				
				// Always re-fetch to ensure we have the correct reference
				$var = $scope->getVarObject($varName);
				if ($var) {
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
			} else {
				// Variable is either unset or null
				if ($var) {
					$var->mayBeNull = true;
					$var->mayBeUnset = true;
					// Add null to type
					$var->type = self::addNull($var->type);
				}
			}
		}
	}
	
	/**
	 * Handle empty() language construct
	 * 
	 * @param Node\Expr\Empty_ $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleEmpty(Node\Expr\Empty_ $node, Scope $scope, bool $truthyBranch): void {
		if (!($node->expr instanceof Node\Expr\Variable) || !is_string($node->expr->name)) {
			return;
		}
		
		$varName = $node->expr->name;
		$var = $scope->getVarObject($varName);
		
		if ($truthyBranch) {
			// Variable is empty (could be unset, null, false, 0, "", etc.)
			if ($var) {
				$var->mayBeNull = true;
				$var->mayBeUnset = true;
			}
		} else {
			// Variable is NOT empty - it's set and truthy
			if (!$var) {
				// Variable doesn't exist yet - create it with mixed type
				$scope->setVarType($varName, TypeComparer::identifierFromName('mixed'), $node->getLine());
				$var = $scope->getVarObject($varName);
			}
			
			if ($var) {
				$var->mayBeNull = false;
				$var->mayBeUnset = false;
				// Remove null from type
				$var->type = self::removeNull($var->type);
			}
		}
	}
	
	/**
	 * Handle type check functions (is_null, is_string, etc.)
	 * 
	 * @param Node\Expr\FuncCall $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleTypeCheckFunction(Node\Expr\FuncCall $node, Scope $scope, bool $truthyBranch): void {
		if (!($node->name instanceof Node\Name)) {
			return;
		}
		
		$funcName = strtolower($node->name->toString());
		
		// Get the first argument (the variable being checked)
		if (empty($node->args) || !($node->args[0]->value instanceof Node\Expr\Variable)) {
			return;
		}
		
		$varNode = $node->args[0]->value;
		if (!is_string($varNode->name)) {
			return;
		}
		
		$varName = $varNode->name;
		$var = $scope->getVarObject($varName);
		
		switch ($funcName) {
			case 'is_null':
				if (!$var) {
					return; // Can't narrow type if variable doesn't exist
				}
				
				if ($truthyBranch) {
					// Variable IS null
					$var->mayBeNull = true;
					$var->mayBeUnset = false; // It's set, just null
					// Set type to null
					$scope->setVarType($varName, TypeComparer::identifierFromName('null'), $node->getLine());
				} else {
					// Variable is NOT null
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'isset':
				// Note: isset() as a function call is deprecated - use Isset_ language construct instead
				if ($truthyBranch) {
					// Variable is set and not null
					if (!$var) {
						// Variable doesn't exist yet - create it with mixed type
						$scope->setVarType($varName, TypeComparer::identifierFromName('mixed'), $node->getLine());
						$var = $scope->getVarObject($varName);
					}
					
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				} else {
					// Variable is either unset or null
					if ($var) {
						// We can't distinguish which, so set both
						$var->mayBeNull = true;
						$var->mayBeUnset = true;
						// Add null to type
						$var->type = self::addNull($var->type);
					}
				}
				break;
				
			case 'is_string':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('string'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'is_int':
			case 'is_integer':
			case 'is_long':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('int'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'is_float':
			case 'is_double':
			case 'is_real':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('float'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'is_bool':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('bool'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'is_array':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('array'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'is_object':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('object'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
				
			case 'is_resource':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('resource'), $node->getLine());
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
				}
				break;
		}
	}
	
	/**
	 * Handle !== null and != null checks
	 * 
	 * @param Node\Expr\BinaryOp $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleNotEqualNull(Node\Expr\BinaryOp $node, Scope $scope, bool $truthyBranch): void {
		$varNode = null;
		$nullNode = null;
		
		// Check if left is variable and right is null
		if ($node->left instanceof Node\Expr\Variable && self::isNullNode($node->right)) {
			$varNode = $node->left;
			$nullNode = $node->right;
		}
		// Check if right is variable and left is null
		elseif ($node->right instanceof Node\Expr\Variable && self::isNullNode($node->left)) {
			$varNode = $node->right;
			$nullNode = $node->left;
		}
		
		if (!$varNode || !is_string($varNode->name)) {
			return;
		}
		
		$varName = $varNode->name;
		$var = $scope->getVarObject($varName);
		
		// If variable doesn't exist, we can't narrow it
		if (!$var) {
			return;
		}
		
		if ($truthyBranch) {
			// Variable is NOT null ($x !== null or $x != null is true)
			$var->mayBeNull = false;
			$var->mayBeUnset = false;
			// Remove null from type
			$var->type = self::removeNull($var->type);
		} else {
			// Variable IS null ($x !== null or $x != null is false)
			$var->mayBeNull = true;
			$var->mayBeUnset = false;
			// Add null to type
			$var->type = self::addNull($var->type);
		}
	}
	
	/**
	 * Handle === null and == null checks
	 * 
	 * @param Node\Expr\BinaryOp $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleEqualNull(Node\Expr\BinaryOp $node, Scope $scope, bool $truthyBranch): void {
		$varNode = null;
		$nullNode = null;
		
		// Check if left is variable and right is null
		if ($node->left instanceof Node\Expr\Variable && self::isNullNode($node->right)) {
			$varNode = $node->left;
			$nullNode = $node->right;
		}
		// Check if right is variable and left is null
		elseif ($node->right instanceof Node\Expr\Variable && self::isNullNode($node->left)) {
			$varNode = $node->right;
			$nullNode = $node->left;
		}
		
		if (!$varNode || !is_string($varNode->name)) {
			return;
		}
		
		$varName = $varNode->name;
		$var = $scope->getVarObject($varName);
		
		if (!$var) {
			return;
		}
		
		if ($truthyBranch) {
			// Variable IS null
			$var->mayBeNull = true;
			$var->mayBeUnset = false;
			$scope->setVarType($varName, TypeComparer::identifierFromName('null'), $node->getLine());
		} else {
			// Variable is NOT null
			$var->mayBeNull = false;
			$var->mayBeUnset = false;
			// Remove null from type
			$var->type = self::removeNull($var->type);
		}
	}
	
	/**
	 * Check if a node represents null
	 * 
	 * @param Node $node
	 * @return bool
	 */
	private static function isNullNode(Node $node): bool {
		return $node instanceof Node\Expr\ConstFetch && 
		       $node->name instanceof Node\Name &&
		       strtolower($node->name->toString()) === 'null';
	}
	
	/**
	 * Remove null from a type
	 * 
	 * @param Node\Name|Node\Identifier|Node\ComplexType|null $type
	 * @return Node\Name|Node\Identifier|Node\ComplexType|null
	 */
	private static function removeNull(Node\Name|Node\Identifier|Node\ComplexType|null $type): Node\Name|Node\Identifier|Node\ComplexType|null {
		if ($type === null) {
			return null;
		}
		
		// If it's a NullableType, return the inner type
		if ($type instanceof Node\NullableType) {
			return $type->type;
		}
		
		// If it's a UnionType, filter out null
		if ($type instanceof Node\UnionType) {
			$nonNullTypes = [];
			foreach ($type->types as $subType) {
				if (!TypeComparer::isNamedIdentifier($subType, 'null')) {
					$nonNullTypes[] = $subType;
				}
			}
			
			if (empty($nonNullTypes)) {
				return null;
			}
			
			if (count($nonNullTypes) === 1) {
				return $nonNullTypes[0];
			}
			
			return new Node\UnionType($nonNullTypes);
		}
		
		// If it's just "null", return null
		if (TypeComparer::isNamedIdentifier($type, 'null')) {
			return null;
		}
		
		// Otherwise return as-is
		return $type;
	}
	
	/**
	 * Add null to a type if not already present
	 * 
	 * @param Node\Name|Node\Identifier|Node\ComplexType|null $type
	 * @return Node\Name|Node\Identifier|Node\ComplexType|null
	 */
	private static function addNull(Node\Name|Node\Identifier|Node\ComplexType|null $type): Node\Name|Node\Identifier|Node\ComplexType|null {
		if ($type === null) {
			return TypeComparer::identifierFromName('null');
		}
		
		// Already nullable
		if ($type instanceof Node\NullableType) {
			return $type;
		}
		
		// Check if it's already null
		if (TypeComparer::isNamedIdentifier($type, 'null')) {
			return $type;
		}
		
		// If it's a union, check if null is already in it
		if ($type instanceof Node\UnionType) {
			foreach ($type->types as $subType) {
				if (TypeComparer::isNamedIdentifier($subType, 'null')) {
					return $type; // Already has null
				}
			}
			
			// Add null to the union
			$types = $type->types;
			$types[] = TypeComparer::identifierFromName('null');
			return new Node\UnionType($types);
		}
		
		// Create a union with null
		return new Node\UnionType([$type, TypeComparer::identifierFromName('null')]);
	}
}
