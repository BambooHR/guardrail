<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\ClassMethod;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node\Expr\Variable;


class PropertyFetch extends BaseCheck
{
	function getCheckNodeTypes() {
		return [ \PhpParser\Node\Expr\PropertyFetch::class ];
	}

	/**
	 * @param                                    $fileName
	 * @param \PhpParser\Node\Expr\PropertyFetch $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		if($node->var instanceof Variable) {
			if(is_string($node->var->name) && $node->var->name=='this') {

				if($inside instanceof Trait_) {
					return;
				}
				if(!$inside) {
					$this->emitError($fileName, $node, self::TYPE_SCOPE_ERROR, "Can't use \$this outside of a class");
					return;
				}
				if(!is_string($node->name)) {
					// Variable method name.  Yuck!
					return;
				}
				//echo "Access ".$node->var->name."->".$node->name."\n";
				$property = Util::findProperty($inside,$node->name, $this->symbolTable);
				if(!$property) {
					//$this->emitError($fileName, $node, "Unknown property", "Accessing unknown property of $inside->namespacedName: \$this->" . $node->name);
					return;
				}
			}
		}
	}
}
