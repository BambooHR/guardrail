<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;

/**
 * Class UnusedPrivateMemberVariableCheck
 * @package BambooHR\Guardrail\Checks
 */
class UnusedPrivateMemberVariableCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	function getCheckNodeTypes() {
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
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		$memberVariables = [];
		if ($node instanceof Property && $node->isPrivate()) {
			$memberVariables[] = $node->props[0]->name;
			$usedVariables = [];
			if ($inside instanceof Class_) {
				foreach ($inside->stmts as $statement) {
					// we will ignore constructors for the purposes of usage
					if ($statement instanceof Node\Stmt\ClassMethod && $statement->name !== '__construct') {
						$getVar = function ($object) use (&$usedVariables) {
							if ($object instanceof Node\Expr\PropertyFetch) {
								$usedVariables[] = $object->name;
							}
						};
						ForEachNode::run($statement->getStmts(), $getVar);
					}
				}
			}
			foreach ($memberVariables as $memberVariable) {
				if (!in_array($memberVariable, $usedVariables)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNUSED_PROPERTY, "Unused private variable detected");
				}
			}
		}
	}
}