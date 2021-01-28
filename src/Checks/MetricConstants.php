<?php namespace BambooHR\Guardrail\Checks;

/**
 * Class ErrorConstants
 *
 * @package BambooHR\Guardrail\Checks
 */
class MetricConstants {


	const TYPE_DEPRECATED_INTERNAL = 'Standard.Deprecated.Internal';
	const TYPE_DEPRECATED_USER = 'Standard.Deprecated.User';
	const TYPE_METHOD_CALL = 'Standard.Method.Call';

	/**
	 * @return string[]
	 */
	static function getConstants() {
		$ret = [];
		$selfReflection = new \ReflectionClass(self::class);
		$constants = $selfReflection->getConstants();
		sort($constants);
		foreach ($constants as $name => $value) {
			$ret[] = $value;
		}
		return $ret;
	}
}