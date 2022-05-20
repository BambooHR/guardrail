<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Builder\Property;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;


class PromotedPropertyVisitor extends NodeVisitorAbstract {

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return null
	 */
	public function enterNode(Node $node) {
		if ($node instanceof Class_) {
			$constructor = $node->getMethod("__construct");
			if($constructor) {
				// For each parameter with a visibility modifier,
				//   -  add a property declaration to the class.
				//   -  add an assignment statement to the constructor
				$newStmts=[];
				$newProps=[];
				foreach ($constructor->getParams() as $param) {
					if ($param->flags & (Class_::MODIFIER_PRIVATE | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PUBLIC)) {
						$newProps[] = $this->buildProp($param);
						$newStmts[] =  $this->buildAssign($param);
					}
				}
				if (count($newProps) > 0) {
					$constructor->stmts = array_merge($newStmts, $constructor->stmts);
					$node->stmts = array_merge($newProps, $node->stmts);
				}
			}
		}
		return null;
	}

	public function buildAssign(Node\Param $param):Node\Expr {
		$attrs = $param->getAttributes();
		return new Node\Expr\Assign(
			new Node\Expr\PropertyFetch(new Node\Expr\Variable("this", $attrs), new Node\Identifier($param->var->name, $attrs), $attrs),
			new Node\Expr\Variable($param->var->name, $attrs),
			$attrs
		);
	}

	public function buildProp(Node\Param $param) {
		$prop = new Property($param->var->name);
		if($param->flags & Class_::MODIFIER_PUBLIC) {
			$prop->makePublic();
		}
		if($param->flags & Class_::MODIFIER_PRIVATE) {
			$prop->makePrivate();
		}
		if($param->flags & Class_::MODIFIER_PROTECTED) {
			$prop->makeProtected();
		}
		if($param->getType()) {
			$prop->setType( $param->getType() );
		}

		$propNode = $prop->getNode();
		// Make sure to copy attributes, so that we get the line numbers
		$propNode->props[0]->setAttributes($param->getAttributes());
		$propNode->props[0]->name->setAttributes($param->getAttributes());
		$propNode->setAttributes($param->getAttributes());
		return $propNode;
	}
}
