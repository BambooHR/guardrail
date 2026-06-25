<?php

class TestUnusedVarsArrowFnTernary {
	function testVariablesUsedInArrowFunctionTernary(): void {
		$results = [1, 2, 3];
		$customFieldValues = ['a', 'b', 'c'];
		$customFields = ['x' => true];

		return array_map(
			fn($fieldType, $fieldId) => isset($customFields[$fieldId]) ? (
				$customFieldValues[0]
			) : (
				$results[0]
			),
			['type1', 'type2'],
			['x', 'y']
		);
	}
}
