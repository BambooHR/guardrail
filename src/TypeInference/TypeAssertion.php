<?php

namespace BambooHR\Guardrail\TypeInference;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
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
	
	private static ?SymbolTable $symbolTable = null;
	
	public static function setSymbolTable(?SymbolTable $table): void {
		self::$symbolTable = $table;
	}
	
	/**
	 * Apply type narrowing based on a condition
	 * 
	 * @param Node $condition The condition being evaluated
	 * @param Scope $scope The scope to modify
	 * @param bool $truthyBranch Whether this is the truthy or falsy branch
	 * @param callable|null $nameResolver Optional name resolver for namespace resolution
	 * @return void
	 */
	public static function narrowTypes(Node $condition, Scope $scope, bool $truthyBranch, ?callable $nameResolver = null): void {
		// Check for docblock type assertions on the previous line
		self::handleDocblockTypeAssertion($condition, $scope, $truthyBranch, $nameResolver);
		
		// instanceof Foo -> $var is Foo in truthy branch
		if ($condition instanceof Node\Expr\Instanceof_) {
			self::handleInstanceOf($condition, $scope, $truthyBranch);
			return;
		}
		
		// if ($var) or if ($obj->prop) -> variable is non-null in truthy branch
		if ($condition instanceof Node\Expr\Variable ||
		    $condition instanceof Node\Expr\PropertyFetch ||
		    $condition instanceof Node\Expr\NullsafePropertyFetch) {
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
			} else {
				// In truthy branch, check if this is a chain of instanceof checks on the same variable
				// Pattern: $var instanceof A || $var instanceof B || $var instanceof C
				$instanceofTypes = self::collectInstanceofTypes($condition);
				if ($instanceofTypes !== null) {
					// We have a chain of instanceof checks on the same variable
					// Narrow to the union of all those types
					[$varName, $types] = $instanceofTypes;
					
					if (count($types) > 0) {
						// Create a union type from all the instanceof types
						$unionType = count($types) === 1 ? $types[0] : new Node\UnionType($types);
						$scope->setVarType($varName, $unionType, $condition->getLine());
						
						// instanceof proves the variable is not null and is set
						$var = $scope->getVarObject($varName);
						if ($var) {
							$var->mayBeNull = false;
							$var->mayBeUnset = false;
						}
					}
				}
				// Otherwise, at least one is true - can't narrow further
			}
			return;
		}
	}
	
	/**
	 * Handle docblock type assertions before assignments
	 * 
	 * @param Node $condition The condition or statement being evaluated
	 * @param Scope $scope The scope to modify
	 * @param bool $truthyBranch Whether this is the truthy or falsy branch
	 * @param callable|null $nameResolver Optional name resolver for namespace resolution
	 * @return void
	 */
	public static function handleDocblockTypeAssertion(Node $condition, Scope $scope, bool $truthyBranch, ?callable $nameResolver = null): void {
		// Get comments from the condition or previous statement
		$comments = $condition->getComments();
		
		// If no comments on the condition, try to get comments from the parent statement
		if (empty($comments)) {
			$parent = $condition->getAttribute('parent');
			if ($parent) {
				$comments = $parent->getComments();
			}
		}
		
		if (empty($comments)) {
			return;
		}
		
		foreach ($comments as $comment) {
			$text = $comment->getText();
			// Only process block comments (/* */ or /** */), not line comments (//)
			if (strpos($text, '/*') === 0) {
				// Match @var Type $var format
				if (preg_match_all('/@var +(?:([-A-Z0-9_|\\\\<>]+(?:\[])*)( +\\$([A-Z0-9_]+))?|(\\$([A-Z0-9_]+)) +([-A-Z0-9_|\\\\<>]+(?:\[])*))/i', $text, $matchArray, PREG_SET_ORDER)) {
					foreach ($matchArray as $tag) {
						$varName = null;
						
						// Handle standard format: @var Type $var (groups 1 and 3)
						if (isset($tag[3]) && !empty($tag[1])) {
							$varName = $tag[3];
							$typeString = $tag[1];
						} elseif (isset($tag[5]) && isset($tag[6])) {
							// Handle reversed format: @var $var Type (groups 5 and 6)
							$varName = $tag[5];
							$typeString = $tag[6];
						}
						
						if ($varName && $typeString && $scope->getVarExists($varName)) {
							try {
								// Parse the type string using the TypeParser with proper namespace resolution
								$resolver = $nameResolver ? \Closure::fromCallable($nameResolver) : fn($fn)=>$fn;
								$typeParser = new \BambooHR\Guardrail\TypeParser($resolver);
								$parsedType = $typeParser->parse($typeString);
								
								if ($truthyBranch) {
									// In truthy branch, assert the variable is this type
									$scope->setVarType($varName, $parsedType, $condition->getLine());
									
									// Update mayBeNull flag based on whether the type is explicitly nullable
									$var = $scope->getVarObject($varName);
									if ($var) {
										// Check if the type string explicitly contains "null" or starts with "?"
										$var->mayBeNull = stripos($typeString, 'null') !== false || str_starts_with($typeString, '?');
										$var->mayBeUnset = false;
									}
								}
							} catch (\BambooHR\Guardrail\Exceptions\DocBlockParserException $e) {
								// Ignore parsing errors
								continue;
							}
						}
					}
				}
			}
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
		if (!($node->class instanceof Node\Name)) {
			return;
		}
		
		// Extract variable name - supports both simple variables and property fetches
		$varName = TypeComparer::getChainedPropertyFetchName($node->expr);
		if (!$varName) {
			return;
		}
		
		$className = $node->class;
		
		if ($truthyBranch) {
			$currentType = $scope->getVarType($varName);
			$currentNonNull = TypeComparer::removeNullOption($currentType);
			$instanceOfName = $className->toString();
			
			// Determine if we should replace the type with the instanceof class.
			// Always replace if: unknown, mixed, object, union, or nullable.
			// For a specific class type: only replace if the instanceof class is
			// more specific (a child), not less specific (a parent).
			$shouldReplace = true;
			if ($currentNonNull instanceof Node\Name && 
				!TypeComparer::isNamedIdentifier($currentNonNull, 'mixed') &&
				!TypeComparer::isNamedIdentifier($currentNonNull, 'object') &&
				!($currentType instanceof Node\UnionType) &&
				!($currentType instanceof Node\NullableType)) {
				$currentName = $currentNonNull->toString();
				if (strcasecmp($currentName, $instanceOfName) === 0) {
					// Same class - no need to replace
					$shouldReplace = false;
				} elseif (self::$symbolTable) {
					// Check hierarchy: if current is already a child of instanceof class, keep it
					if (self::$symbolTable->isParentClassOrInterface($instanceOfName, $currentName)) {
						$shouldReplace = false;
					}
				}
			}
			
			if ($shouldReplace) {
				$scope->setVarType($varName, $className, $node->getLine());
			}
			
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
	 * Handle truthiness checks (if ($var) or if ($obj->prop))
	 * 
	 * @param Node\Expr\Variable|Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch $node
	 * @param Scope $scope
	 * @param bool $truthyBranch
	 * @return void
	 */
	private static function handleTruthyCheck(Node\Expr\Variable|Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch $node, Scope $scope, bool $truthyBranch): void {
		// Extract variable name - supports both simple variables and property fetches
		$varName = TypeComparer::getChainedPropertyFetchName($node);
		if (!$varName) {
			return;
		}
		
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
			// Support both simple variables and property fetches
			$varName = TypeComparer::getChainedPropertyFetchName($varNode);
			if (!$varName) {
				continue;
			}
			
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
				
				// If this is a property fetch (e.g., isset($obj->prop)), also narrow all parent objects
				// because you can't access properties on null
				if ($varNode instanceof Node\Expr\PropertyFetch || $varNode instanceof Node\Expr\NullsafePropertyFetch) {
					self::narrowParentChain($varNode->var, $scope, $node->getLine());
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
	 * Narrow all parent objects in a property fetch chain to non-null.
	 * For example, $a->b->c->d means $a, $a->b, and $a->b->c must all be non-null.
	 */
	private static function narrowParentChain(Node $currentNode, Scope $scope, int $line): void {
		while ($currentNode) {
			$parentName = TypeComparer::getChainedPropertyFetchName($currentNode);
			if ($parentName) {
				$parentVar = $scope->getVarObject($parentName);
				if (!$parentVar) {
					// Create the var with inferred type so we can narrow it
					$inferredType = $currentNode->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
					$scope->setVarType($parentName, $inferredType, $line);
					$parentVar = $scope->getVarObject($parentName);
				}
				if ($parentVar) {
					$parentVar->mayBeNull = false;
					$parentVar->mayBeUnset = false;
					$parentVar->type = self::removeNull($parentVar->type);
				}
			}
			
			// Move up the chain
			if ($currentNode instanceof Node\Expr\PropertyFetch || $currentNode instanceof Node\Expr\NullsafePropertyFetch) {
				$currentNode = $currentNode->var;
			} else {
				break;
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
	
	// Get the first argument (the variable or property being checked)
	if (empty($node->args)) {
		return;
	}
	
	$varNode = $node->args[0]->value;
	
	// Support both simple variables and property fetches
	$varName = TypeComparer::getChainedPropertyFetchName($varNode);
	if (!$varName) {
		return;
	}
	
	$var = $scope->getVarObject($varName);
	if($var === null) {
		// Initialize with inferred type from AST so narrowing has something to work with
		$inferredType = $varNode?->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		$scope->setVarType($varName, $inferredType, $node->getLine());
		// Re-fetch from scope so modifications below affect the actual scope var
		$var = $scope->getVarObject($varName);
	}
		
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
				} else if ($var) {
					// Variable is NOT null
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
					// Remove null from type
					$var->type = self::removeNull($var->type);
					// Also narrow parent chain for property fetches
					if ($varNode instanceof Node\Expr\PropertyFetch || $varNode instanceof Node\Expr\NullsafePropertyFetch) {
						self::narrowParentChain($varNode->var, $scope, $node->getLine());
					}
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
					} else {
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
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				} else if ($var) {
					$var->mayBeNull = true;
					$var->mayBeUnset = false;
					// Add null to type
					$var->type = self::removeNamedTypeFromUnion(self::addNull($var->type),'string');
				}
				break;
				
			case 'is_int':
			case 'is_integer':
			case 'is_long':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('int'), $node->getLine());
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				} else if ($var) {
					$var->mayBeNull = true;
					$var->mayBeUnset = false;
					// Add null to type
					$var->type = self::removeNamedTypeFromUnion(self::addNull($var->type),'int');
				}
				break;
				
			case 'is_float':
			case 'is_double':
			case 'is_real':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('float'), $node->getLine());
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				}
				break;
				
			case 'is_bool':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('bool'), $node->getLine());
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				}
				break;
				
			case 'is_array':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('array'), $node->getLine());
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				} else {
					// In falsy branch, we know it's NOT an array
					// Remove 'array' from union types
					// First get the current type - either from scope or from the variable object
					$currentType = $scope->getVarType($varName);
					if (!$currentType && $var) {
						$currentType = $var->type;
					}
					
					if ($currentType) {
						$newType = self::removeNamedTypeFromUnion($currentType, 'array');
						if ($newType !== null) {
							$scope->setVarType($varName, $newType, $node->getLine());
						}
					}
				}
				break;
				
			case 'is_object':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('object'), $node->getLine());
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				}
				break;
				
			case 'is_resource':
				if ($truthyBranch) {
					$scope->setVarType($varName, TypeComparer::identifierFromName('resource'), $node->getLine());
					$var = $scope->getVarObject($varName);
					if ($var) {
						$var->mayBeNull = false;
						$var->mayBeUnset = false;
						// Remove null from type
						$var->type = self::removeNull($var->type);
					}
				}
				break;
				
			case 'property_exists':
				// property_exists($obj, "prop") - if true, $obj must not be null
				// Only narrow if both arguments are present and the second is a string literal
				if ($truthyBranch && $var && count($node->args) >= 2 && $node->args[1]->value instanceof Node\Scalar\String_) {
					$var->mayBeNull = false;
					$var->mayBeUnset = false;
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
		
		// Check if left is variable/property and right is null
		if (self::isNullNode($node->right)) {
			$varNode = $node->left;
		}
		// Check if right is variable/property and left is null
		elseif (self::isNullNode($node->left)) {
			$varNode = $node->right;
		}
		
		if (!$varNode) {
			return;
		}
		
		// Extract variable name - supports both simple variables and property fetches
		$varName = TypeComparer::getChainedPropertyFetchName($varNode);
		if (!$varName) {
			return;
		}
		
		$var = $scope->getVarObject($varName);
		
		// If variable doesn't exist, create it with inferred type so narrowing works
		if (!$var) {
			$inferredType = $varNode->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			$scope->setVarType($varName, $inferredType, $node->getLine());
			$var = $scope->getVarObject($varName);
		}
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
		
		// Check if left is variable/property and right is null
		if (self::isNullNode($node->right)) {
			$varNode = $node->left;
		}
		// Check if right is variable/property and left is null
		elseif (self::isNullNode($node->left)) {
			$varNode = $node->right;
		}
		
		if (!$varNode) {
			return;
		}
		
		// Extract variable name - supports both simple variables and property fetches
		$varName = TypeComparer::getChainedPropertyFetchName($varNode);
		if (!$varName) {
			return;
		}
		
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
	 * Remove a named type from a union type
	 * 
	 * @param Node\Name|Node\Identifier|Node\ComplexType|null $type
	 * @param string $typeName The type name to remove (e.g., 'array', 'string')
	 * @return Node\Name|Node\Identifier|Node\ComplexType|null
	 */
	private static function removeNamedTypeFromUnion(Node\Name|Node\Identifier|Node\ComplexType|null $type, string $typeName): Node\Name|Node\Identifier|Node\ComplexType|null {
		if ($type === null) {
			return null;
		}
		
		// If it's a UnionType, filter out the named type
		if ($type instanceof Node\UnionType) {
			$remainingTypes = [];
			foreach ($type->types as $subType) {
				if (!TypeComparer::isNamedIdentifier($subType, $typeName)) {
					$remainingTypes[] = $subType;
				}
			}
			
			if (empty($remainingTypes)) {
				return null;
			}
			
			if (count($remainingTypes) === 1) {
				return $remainingTypes[0];
			}
			
			return new Node\UnionType($remainingTypes);
		}
		
		// If it's just the named type we're removing, return null
		if (TypeComparer::isNamedIdentifier($type, $typeName)) {
			return null;
		}
		
		// Otherwise return as-is
		return $type;
	}
	
	/**
	 * Collect instanceof types from a chain of OR expressions
	 * Returns [varName, [types]] if all checks are on the same variable, null otherwise
	 * 
	 * @param Node $node
	 * @return array|null
	 */
	private static function collectInstanceofTypes(Node $node): ?array {
		$varName = null;
		$types = [];
		
		if (!self::collectInstanceofTypesFromNode($node, $varName, $types)) {
			return null;
		}
		
		return [$varName, $types];
	}
	
	/**
	 * Recursively collect instanceof types from a node
	 * 
	 * @param Node $node
	 * @param string|null $varName
	 * @param array $types
	 * @return bool True if all instanceof checks are on the same variable
	 */
	private static function collectInstanceofTypesFromNode(Node $node, ?string &$varName, array &$types): bool {
		if ($node instanceof Node\Expr\Instanceof_) {
			// Extract variable name
			$currentVarName = TypeComparer::getChainedPropertyFetchName($node->expr);
			if ($currentVarName === null || !($node->class instanceof Node\Name)) {
				return false;
			}
			
			// Check if this is the same variable as previous checks
			if ($varName === null) {
				$varName = $currentVarName;
			} elseif ($varName !== $currentVarName) {
				// Different variable - can't create union
				return false;
			}
			
			$types[] = $node->class;
			return true;
		} elseif ($node instanceof Node\Expr\BinaryOp\BooleanOr) {
			// Recursively check both sides
			return self::collectInstanceofTypesFromNode($node->left, $varName, $types) &&
			       self::collectInstanceofTypesFromNode($node->right, $varName, $types);
		}
		
		return false;
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
