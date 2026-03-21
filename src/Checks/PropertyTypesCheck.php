<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;

/**
 * Class PropertyTypesCheck
 * 
 * Validates property type declarations, including restrictions on callable types
 *
 * @package BambooHR\Guardrail\Checks
 */
class PropertyTypesCheck extends BaseCheck {
	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Property::class];
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
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Property) {
			$type = $node->type;
			
			if ($type !== null) {
				$invalidType = $this->findInvalidPropertyType($type);
				if ($invalidType !== null) {
					$propertyNames = array_filter(array_map(fn(\PhpParser\Node\Stmt\PropertyProperty $prop) => $prop->name !== null ? '$' . $prop->name : null, $node->props));
					$propertyList = implode(', ', $propertyNames);
					
					$message = match($invalidType) {
						'callable' => "Property $propertyList cannot be declared with callable type. Use a Closure type or specific interface instead.",
						'void' => "Property $propertyList cannot be declared with void type. Properties must have a value type.",
						'never' => "Property $propertyList cannot be declared with never type. Properties must have a value type.",
						'true', 'false' => "Property $propertyList cannot be declared with $invalidType type. Use bool instead.",
						default => "Property $propertyList cannot be declared with $invalidType type."
					};
					
					$this->emitError(
						$fileName, 
						$node, 
						ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE, 
						$message
					);
				}
			}
		}
	}
	
	/**
	 * Check if a type contains invalid property types (callable, never, void, true, false)
	 *
	 * @param Node\ComplexType|Node\Identifier|Node\Name $type
	 * @return string|null The invalid type name if found, null otherwise
	 */
	private function findInvalidPropertyType(Node\ComplexType|Node\Identifier|Node\Name $type): ?string {
		$invalidTypes = ['callable', 'never', 'void', 'true', 'false'];
		
		// Check if it's directly one of the invalid types
		foreach ($invalidTypes as $invalidType) {
			if (TypeComparer::isNamedIdentifier($type, $invalidType)) {
				return $invalidType;
			}
		}
		
		// Check union types
		if ($type instanceof Node\UnionType) {
			foreach ($type->types as $subType) {
				$invalid = $this->findInvalidPropertyType($subType);
				if ($invalid !== null) {
					return $invalid;
				}
			}
		}
		
		// Check intersection types
		if ($type instanceof Node\IntersectionType) {
			foreach ($type->types as $subType) {
				$invalid = $this->findInvalidPropertyType($subType);
				if ($invalid !== null) {
					return $invalid;
				}
			}
		}
		
		// Check nullable types
		if ($type instanceof Node\NullableType) {
			return $this->findInvalidPropertyType($type->type);
		}
		
		return null;
	}
}
