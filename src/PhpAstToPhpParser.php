<?php

/**
 * Guardrail.  Copyright (c) 2018, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

use ast\Node;
use ast\Node\Decl;


/**
 * This class converts a php-ast tree (version 50) to the equivalent PhpParser tree (version 3.1).
 *
 * php-ast is a low level tree.  Certain syntactic details are not available. A few examples:
 *
 * - LOGICAL_AND and LOGICAL_OR aren't used.  They convert to BooleanAnd/BooleanOr.  Their placement in the tree will
 *   have the correct precedence.
 * - InlineHTML doesn't exist in php-ast.  It converts to an "echo" statement.
 * - DocBlocks are only included for key elements in php-ast. Those include: classes, functions, and methods.
 * - Comments other than DocBlocks are completely stripped.
 * - Property look ups of the form $obj->{"propertyString"} will be converted to $obj->propertyString
 * - unset($foo, $baz) will be expanded to unset($foo); unset($baz);
 * - isset($foo, $baz) will be expanded to isset($foo) && isset($baz)
 * - echo "foo", $bar, "baz" will be expanded to echo "foo"; echo $bar; echo "baz";
 *
 * If we're ok with these differences, then this approach will build a PhpParser tree much faster than parsing directly
 * with PhpParser.  (2x faster minimum in all the tests I've done.)
 *
 */
class PhpAstToPhpParser {
	/** @var bool */
	private $includeMethodBodies = true;

	/**
	 * @return void
	 */
	function skipMethodBodies() {
		$this->includeMethodBodies = false;
	}

	/**
	 * @return void
	 */
	function includeMethodBodies() {
		$this->includeMethodBodies = true;
	}

	/**
	 * @param array|null|Node $nodes A statement list or array that should be converted and returned as a PHP array.
	 * @return array
	 */
	function convertAstNodeArray($nodes = null) {
		if ($nodes === null) {
			return [];
		} else if (is_array($nodes)) {
			return array_map([$this, 'convertAstNode'], $nodes);
		} else if ($nodes instanceof Node && ($nodes->kind == \ast\AST_STMT_LIST || $nodes->kind == \ast\AST_NAME_LIST)) {
			return $this->convertAstNodeArray($nodes->children);
		};
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt|\PhpParser\Node\Expr
	 */
	public function convertAstNode($node) {
		if ($node === null) {
			return null;
		}
		if (!$node instanceof Node) {
			switch (gettype($node)) {
				case 'integer':
				case 'double':
				case 'boolean':
				case 'string':
					return $this->scalarToNode($node, 0);
				default:
					// an array, null, etc, should never come through here
					throw new PhpAstToPhpParserException("Unknown type ('. gettype($node) . ') found.\n");
			}
		}

		switch ($node->kind) {
			case \ast\AST_ARG_LIST:
				$ret = $this->argList($node);
				break;
			case \ast\AST_ARRAY:
				$ret = $this->arrayExpr($node);
				break;
			case \ast\AST_ARRAY_ELEM:
				$ret = $this->arrayElem($node);
				break;
			case \ast\AST_ASSIGN:
				$ret = $this->assign($node);
				break;
			case \ast\AST_ASSIGN_OP:
				$ret = $this->assignOp($node);
				break;
			case \ast\AST_ASSIGN_REF:
				$ret = $this->assignRef($node);
				break;
			case \ast\AST_BINARY_OP:
				$ret = $this->binaryOp($node);
				break;
			case \ast\AST_BREAK:
				$ret = $this->breakStmt($node);
				break;
			case \ast\AST_CALL:
				$ret = $this->call($node);
				break;
			case \ast\AST_CAST:
				$ret = $this->cast($node);
				break;
			case \ast\AST_CATCH:
				$ret = $this->catchStmt($node);
				break;
			case \ast\AST_CATCH_LIST:
				$ret = $this->catchList($node);
				break;
			case \ast\AST_CLASS:
				$ret = $this->classStmt($node);
				break;
			case \ast\AST_CLASS_CONST:
				$ret = $this->classConst($node);
				break;
			case \ast\AST_CLASS_CONST_DECL:
				$ret = $this->classConstDecl($node);
				break;
			case \ast\AST_CLONE:
				$ret = $this->cloneStmt($node);
				break;
			case \ast\AST_CLOSURE:
				$ret = $this->closure($node);
				break;
			case \ast\AST_CLOSURE_USES:
				$ret = $this->convertAstNodeArray($node->children);
				break;
			case \ast\AST_CLOSURE_VAR:
				$ret = $this->closureVar($node);
				break;
			case \ast\AST_COALESCE:
				$ret = $this->coalesce($node);
				break;
			case \ast\AST_CONDITIONAL:
				$ret = $this->conditional($node);
				break;
			case \ast\AST_CONST:
				$ret = $this->constStmt($node);
				break;
			case \ast\AST_CONST_DECL:
				$ret = $this->constDecl($node);
				break;
			case \ast\AST_CONST_ELEM:
				$ret = $this->constElem($node);
				break;
			case \ast\AST_CONTINUE:
				$ret = $this->continueStmt($node);
				break;
			case \ast\AST_DECLARE:
				$ret = $this->declareStmt($node);
				break;
			case \ast\AST_DIM:
				$ret = $this->dim($node);
				break;
			case \ast\AST_DO_WHILE:
				$ret = $this->doWhile($node);
				break;
			case \ast\AST_ECHO:
				$ret = $this->echoStmt($node);
				break;
			case \ast\AST_EMPTY:
				$ret = $this->emptyExpr($node);
				break;
			case \ast\AST_ENCAPS_LIST:
				$ret = $this->encapsList($node);
				break;
			case \ast\AST_EXIT:
				$ret = $this->exitExpr($node);
				break;
			case \ast\AST_EXPR_LIST:
				$ret = $this->convertAstNodeArray($node->children);
				break;
			case \ast\AST_FOR:
				$ret = $this->forStmt($node);
				break;
			case \ast\AST_FOREACH:
				$ret = $this->foreachStmt($node);
				break;
			case \ast\AST_FUNC_DECL:
				$ret = $this->funcDecl($node);
				break;
			case \ast\AST_GLOBAL:
				$ret = $this->globalStmt($node);
				break;
			case \ast\AST_GOTO:
				$ret = $this->gotoStmt($node);
				break;
			case \ast\AST_GROUP_USE:
				$ret = $this->groupUse($node);
				break;
			case \ast\AST_HALT_COMPILER:
				$ret = $this->haltCompiler($node);
				break;
			case \ast\AST_IF:
				$ret = $this->ifStmt($node);
				break;
			case \ast\AST_IF_ELEM:
				$ret = $this->ifElem($node);
				break;
			case \ast\AST_INCLUDE_OR_EVAL:
				$ret = $this->includeOrEval($node);
				break;
			case \ast\AST_INSTANCEOF:
				$ret = $this->instanceofExpr($node);
				break;
			case \ast\AST_ISSET:
				$ret = $this->issetExpr($node);
				break;
			case \ast\AST_LABEL:
				$ret = $this->label($node);
				break;
			case \ast\AST_LIST:
				$ret = $this->listExpr($node);
				break;
			case \ast\AST_MAGIC_CONST:
				$ret = $this->magicConst($node);
				break;
			case \ast\AST_METHOD:
				$ret = $this->method($node);
				break;
			case \ast\AST_METHOD_CALL:
				$ret = $this->methodCall($node);
				break;
			case \ast\AST_NAME:
				$ret = $this->name($node);
				break;
			case \ast\AST_NAMESPACE:
				$ret = $this->namespaceStmt($node);
				break;
			case \ast\AST_NAME_LIST:
				$ret = $this->convertAstNodeArray($node->children);
				break;
			case \ast\AST_NEW:
				$ret = $this->newExpr($node);
				break;
			case \ast\AST_NULLABLE_TYPE:
				$ret = $this->nullableType($node);
				break;
			case \ast\AST_PARAM:
				$ret = $this->param($node);
				break;
			case \ast\AST_PARAM_LIST:
				$ret = $this->paramList($node);
				break;
			case \ast\AST_POST_DEC:
				$ret = $this->postDec($node);
				break;
			case \ast\AST_POST_INC:
				$ret = $this->postInc($node);
				break;
			case \ast\AST_PRE_DEC:
				$ret = $this->preDec($node);
				break;
			case \ast\AST_PRE_INC:
				$ret = $this->preInc($node);
				break;
			case \ast\AST_PRINT:
				$ret = $this->printExpr($node);
				break;
			case \ast\AST_PROP:
				$ret = $this->prop($node);
				break;
			case \ast\AST_PROP_DECL:
				$ret = $this->propDecl($node);
				break;
			case \ast\AST_PROP_ELEM:
				$ret = $this->propElem($node);
				break;
			case \ast\AST_RETURN :
				$ret = $this->returnExpr($node);
				break;
			case \ast\AST_SHELL_EXEC:
				$ret = $this->shellExec($node);
				break;
			case \ast\AST_STATIC:
				$ret = $this->staticStmt($node);
				break;
			case \ast\AST_STATIC_CALL:
				$ret = $this->staticCall($node);
				break;
			case \ast\AST_STATIC_PROP:
				$ret = $this->staticProp($node);
				break;
			case \ast\AST_STMT_LIST:
				$ret = $this->stmtList($node);
				break;
			case \ast\AST_SWITCH:
				$ret = $this->switchStmt($node);
				break;
			case \ast\AST_SWITCH_CASE:
				$ret = $this->switchCase($node);
				break;
			case \ast\AST_SWITCH_LIST:
				$ret = $this->switchList($node);
				break;
			case \ast\AST_THROW:
				$ret = $this->throwStmt($node);
				break;
			case \ast\AST_TRAIT_ADAPTATIONS:
				$ret = $this->traitAdaptations($node);
				break;
			case \ast\AST_TRAIT_ALIAS:
				$ret = $this->traitAlias($node);
				break;
			case \ast\AST_TRAIT_PRECEDENCE:
				$ret = $this->traitPrecedence($node);
				break;
			case \ast\AST_TRY:
				$ret = $this->tryStmt($node);
				break;
			case \ast\AST_TYPE:
				$ret = $this->type($node);
				break;
			case \ast\AST_UNARY_OP:
				$ret = $this->unaryOp($node);
				break;
			case \ast\AST_UNSET:
				$ret = $this->unsetExpr($node);
				break;
			case \ast\AST_USE:
				$ret = $this->useStmt($node);
				break;
			case \ast\AST_USE_ELEM:
				$ret = $this->useElem($node);
				break;
			case \ast\AST_USE_TRAIT:
				$ret = $this->useTrait($node);
				break;
			case \ast\AST_VAR:
				$ret = $this->variable($node);
				break;
			case \ast\AST_WHILE:
				$ret = $this->whileStmt($node);
				break;
			case \ast\AST_YIELD:
				$ret = $this->yieldStmt($node);
				break;
			case \ast\AST_YIELD_FROM:
				$ret = $this->yieldFrom($node);
				break;
			default:
				throw new PhpAstToPhpParserException('Unknown AST kind (' . \ast\get_kind_name($node->kind) . ') found on line ' . $node->lineno);
		}
		if ($ret instanceof \PhpParser\Node) {
			$ret->setAttribute('startLine', $node->lineno);
			if (isset($node->children['docComment'])) {
				$ret->setAttribute('comments', [new \PhpParser\Comment\Doc($node->children['docComment'])]);
			}
		}
		return $ret;
	}

	/**
	 * @param Node $node -
	 * @return array
	 */
	private function argList(Node $node) {
		$ret = [];
		foreach ($node->children as $childNode) {
			$unpack = false;
			$ref = false;
			if (is_object($childNode)) {
				if ($childNode->kind == \ast\AST_UNPACK) {
					$unpack = true;
					$childNode = $childNode->children['expr'];
				} else if ($childNode->kind == \ast\AST_REF) {
					$ref = true;
					$childNode = $childNode->children['expr'];
				}
			}
			if (is_string($childNode)) {
				$newChildNode = new \PhpParser\Node\Scalar\String_($childNode, ["startLine" => $node->lineno]);
			} else if (is_int($childNode)) {
				$newChildNode = new \PhpParser\Node\Scalar\LNumber($childNode, ["startLine" => $node->lineno]);
			} else {
				$newChildNode = $this->convertAstNode($childNode);
			}
			$ret[] = new \PhpParser\Node\Arg($newChildNode, $ref, $unpack);

		}
		return $ret;
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Array_|\PhpParser\Node\Expr\List_
	 */
	private function arrayExpr(Node $node) {
		switch ($node->flags) {
			case \ast\flags\ARRAY_SYNTAX_LIST:
				return new \PhpParser\Node\Expr\List_($this->convertAstNodeArray($node->children));
			case \ast\flags\ARRAY_SYNTAX_LONG:
			case \ast\flags\ARRAY_SYNTAX_SHORT:
			case 0:
				return new \PhpParser\Node\Expr\Array_($this->convertAstNodeArray($node->children));
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_ARRAY found.");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\ArrayItem
	 */
	private function arrayElem(Node $node) {
		return new \PhpParser\Node\Expr\ArrayItem(
			$this->convertAstNode($node->children['value']),
			isset($node->children['key']) ? $this->convertAstNode($node->children['key']) : null,
			$node->flags === \ast\flags\PARAM_REF
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Assign
	 */
	private function assign(Node $node) {
		return new \PhpParser\Node\Expr\Assign($this->convertAstNode($node->children['var']), $this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr
	 */
	private function assignOp(Node $node) {
		$var = $this->convertAstNode($node->children['var']);
		$expr = $this->convertAstNode($node->children['expr']);

		switch ($node->flags) {
			case \ast\flags\BINARY_BITWISE_OR:
				return new \PhpParser\Node\Expr\AssignOp\BitwiseOr($var, $expr);
			case \ast\flags\BINARY_BITWISE_AND:
				return new \PhpParser\Node\Expr\AssignOp\BitwiseAnd($var, $expr);
			case \ast\flags\BINARY_BITWISE_XOR:
				return new \PhpParser\Node\Expr\AssignOp\BitwiseXor($var, $expr);
			case \ast\flags\BINARY_CONCAT:
				return new \PhpParser\Node\Expr\AssignOp\Concat($var, $expr);
			case \ast\flags\BINARY_ADD:
				return new \PhpParser\Node\Expr\AssignOp\Plus($var, $expr);
			case \ast\flags\BINARY_SUB:
				return new \PhpParser\Node\Expr\AssignOp\Minus($var, $expr);
			case \ast\flags\BINARY_MUL:
				return new \PhpParser\Node\Expr\AssignOp\Mul($var, $expr);
			case \ast\flags\BINARY_DIV:
				return new \PhpParser\Node\Expr\AssignOp\Div($var, $expr);
			case \ast\flags\BINARY_MOD:
				return new \PhpParser\Node\Expr\AssignOp\Mod($var, $expr);
			case \ast\flags\BINARY_POW:
				return new \PhpParser\Node\Expr\AssignOp\Pow($var, $expr);
			case \ast\flags\BINARY_SHIFT_LEFT:
				return new \PhpParser\Node\Expr\AssignOp\ShiftLeft($var, $expr);
			case \ast\flags\BINARY_SHIFT_RIGHT:
				return new \PhpParser\Node\Expr\AssignOp\ShiftRight($var, $expr);
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_ASSIGN_OP found on line " . $node->lineno);
		}
	}


	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\AssignRef
	 */
	private function assignRef(Node $node) {
		return new \PhpParser\Node\Expr\AssignRef($this->convertAstNode($node->children['var']), $this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node
	 * @return \PhpParser\Node\Expr
	 */
	private function binaryOp(Node $node) {
		$left = $this->convertAstNode($node->children['left']);
		$right = $this->convertAstNode($node->children['right']);

		switch ($node->flags) {
			case \ast\flags\BINARY_BITWISE_OR:
				return new \PhpParser\Node\Expr\BinaryOp\BitwiseOr($left, $right);
			case \ast\flags\BINARY_BITWISE_AND:
				return new \PhpParser\Node\Expr\BinaryOp\BitwiseAnd($left, $right);
			case \ast\flags\BINARY_BITWISE_XOR:
				return new \PhpParser\Node\Expr\BinaryOp\BitwiseXor($left, $right);
			case \ast\flags\BINARY_CONCAT:
				return new \PhpParser\Node\Expr\BinaryOp\Concat($left, $right);
			case \ast\flags\BINARY_ADD:
				return new \PhpParser\Node\Expr\BinaryOp\Plus($left, $right);
			case \ast\flags\BINARY_SUB:
				return new \PhpParser\Node\Expr\BinaryOp\Minus($left, $right);
			case \ast\flags\BINARY_MUL:
				return new \PhpParser\Node\Expr\BinaryOp\Mul($left, $right);
			case \ast\flags\BINARY_DIV:
				return new \PhpParser\Node\Expr\BinaryOp\Div($left, $right);
			case \ast\flags\BINARY_MOD:
				return new \PhpParser\Node\Expr\BinaryOp\Mod($left, $right);
			case \ast\flags\BINARY_POW:
				return new \PhpParser\Node\Expr\BinaryOp\Pow($left, $right);
			case \ast\flags\BINARY_SHIFT_LEFT:
				return new \PhpParser\Node\Expr\BinaryOp\ShiftLeft($left, $right);
			case \ast\flags\BINARY_SHIFT_RIGHT:
				return new \PhpParser\Node\Expr\BinaryOp\ShiftRight($left, $right);
			case \ast\flags\BINARY_BOOL_XOR:
				return new \PhpParser\Node\Expr\BinaryOp\LogicalXor($left, $right);
			case \ast\flags\BINARY_BOOL_OR:
				return new \PhpParser\Node\Expr\BinaryOp\BooleanOr($left, $right);
			case \ast\flags\BINARY_BOOL_AND:
				return new \PhpParser\Node\Expr\BinaryOp\BooleanAnd($left, $right);
			case \ast\flags\BINARY_IS_IDENTICAL:
				return new \PhpParser\Node\Expr\BinaryOp\Identical($left, $right);
			case \ast\flags\BINARY_IS_NOT_IDENTICAL:
				return new \PhpParser\Node\Expr\BinaryOp\NotIdentical($left, $right);
			case \ast\flags\BINARY_IS_EQUAL:
				return new \PhpParser\Node\Expr\BinaryOp\Equal($left, $right);
			case \ast\flags\BINARY_IS_NOT_EQUAL:
				return new \PhpParser\Node\Expr\BinaryOp\NotEqual($left, $right);
			case \ast\flags\BINARY_IS_SMALLER:
				return new \PhpParser\Node\Expr\BinaryOp\Smaller($left, $right);
			case \ast\flags\BINARY_IS_SMALLER_OR_EQUAL:
				return new \PhpParser\Node\Expr\BinaryOp\SmallerOrEqual($left, $right);
			case \ast\flags\BINARY_IS_GREATER:
				return new \PhpParser\Node\Expr\BinaryOp\Greater($left, $right);
			case \ast\flags\BINARY_IS_GREATER_OR_EQUAL:
				return new \PhpParser\Node\Expr\BinaryOp\GreaterOrEqual($left, $right);
			case \ast\flags\BINARY_SPACESHIP:
				return new \PhpParser\Node\Expr\BinaryOp\Spaceship($left, $right);
			case \ast\flags\BINARY_COALESCE:
				return new \PhpParser\Node\Expr\BinaryOp\Coalesce($left, $right);
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_BINARY_OP found.");
		}
	}

	/**
	 * @param Node @node -
	 * @return \PhpParser\Node\Stmt\Break_
	 */
	private function breakStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Break_($this->convertAstNode($node->children['depth']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\FuncCall
	 */
	private function call(Node $node) {
		return new \PhpParser\Node\Expr\FuncCall($this->convertAstNode($node->children['expr']), $this->convertAstNode($node->children['args']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Cast
	 */
	private function cast(Node $node) {
		$expr = $this->convertAstNode($node->children['expr']);

		switch ($node->flags) {
			case \ast\flags\TYPE_NULL:
				return new \PhpParser\Node\Expr\Cast\Unset_($expr);
			case \ast\flags\TYPE_BOOL:
				return new \PhpParser\Node\Expr\Cast\Bool_($expr);
			case \ast\flags\TYPE_LONG:
				return new \PhpParser\Node\Expr\Cast\Int_($expr);
			case \ast\flags\TYPE_DOUBLE:
				return new \PhpParser\Node\Expr\Cast\Double($expr);
			case \ast\flags\TYPE_STRING:
				return new \PhpParser\Node\Expr\Cast\String_($expr);
			case \ast\flags\TYPE_ARRAY:
				return new \PhpParser\Node\Expr\Cast\Array_($expr);
			case \ast\flags\TYPE_OBJECT:
				return new \PhpParser\Node\Expr\Cast\Object_($expr);
			default:
				throw new PhpAstToPhpParserException("Unknown cast type ({$node->flags}) for AST_CAST found.");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Catch_
	 */
	private function catchStmt(Node $node) {
		/** @var \PhpParser\Node\Expr\Variable $variable */
		$variable = $this->convertAstNode($node->children['var']);
		return new \PhpParser\Node\Stmt\Catch_(
			$this->convertAstNode($node->children['class']),
			strval($variable->name),
			$this->convertAstNodeArray($node->children['stmts'])
		);
	}

	/**
	 * @param Node $node -
	 * @return array
	 */
	private function catchList(Node $node) {
		return $this->convertAstNodeArray($node->children);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_|\PhpParser\Node\Stmt\Trait_
	 */
	private function classStmt(Node $node) {
		$subNodes = [];
		if ($node->children['extends'] !== null) {
			$subNodes['extends'] = $this->convertAstNode($node->children['extends']);
		}
		if ($node->children['implements'] !== null) {
			$subNodes['implements'] = $this->convertAstNode($node->children['implements']);
		}
		if ($node->children['stmts'] !== null) {
			$subNodes['stmts'] = $this->convertAstNodeArray($node->children['stmts']);
		}

		if (\ast\flags\CLASS_TRAIT & $node->flags) {

			return new \PhpParser\Node\Stmt\Trait_($node->children['name'], $subNodes);
		} else if (\ast\flags\CLASS_INTERFACE & $node->flags) {
			if (isset($subNodes['implements'])) {
				$subNodes['extends'] = $subNodes['implements'];
				unset($subNodes['implements']);
			}
			return new \PhpParser\Node\Stmt\Interface_($node->children['name'], $subNodes);
		} else {
			$modifier = 0;
			switch ($node->flags) {
				case \ast\flags\CLASS_ABSTRACT:
					$modifier = \PhpParser\Node\Stmt\Class_::MODIFIER_ABSTRACT;
					break;
				case \ast\flags\CLASS_FINAL:
					$modifier = \PhpParser\Node\Stmt\Class_::MODIFIER_FINAL;
					break;
			}
			$subNodes['flags'] = $modifier;
			return new \PhpParser\Node\Stmt\Class_($node->children['name'], $subNodes);
		}
	}

	/**
	 * @param Node $node -
	 * @return string
	 */
	private function getNodeName(Node $node) {
		if ($node instanceof Decl) {
			return $node->name ?? '';
		}

		return $node->children['name'] ?? '';
	}


	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\ClassConstFetch
	 */
	private function classConst(Node $node) {
		return new \PhpParser\Node\Expr\ClassConstFetch(
			$this->convertAstNode($node->children['class']),
			strval($node->children['const'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\ClassConst
	 */
	private function classConstDecl(Node $node) {

		$flag = 0;

		switch ($node->flags) {
			case \ast\flags\MODIFIER_PUBLIC:
				$flag = \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC;
				break;
			case \ast\flags\MODIFIER_PROTECTED:
				$flag = \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED;
				break;
			case \ast\flags\MODIFIER_PRIVATE:
				$flag = \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE;
				break;
			case 0:
				// nothing to do - for PHP 7.0
				break;
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_CONST_DECL found.");
		}
		return new \PhpParser\Node\Stmt\ClassConst($this->convertAstNodeArray($node->children), $flag);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Clone_
	 */
	private function cloneStmt(Node $node) {
		return new \PhpParser\Node\Expr\Clone_($this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Closure
	 */
	private function closure(Node $node) {
		$subNodes = [
			"byRef" => $node->flags === \ast\flags\RETURNS_REF,
			"params" => $this->convertAstNode($node->children['params']),
			"uses" => $this->convertAstNode($node->children['uses']),
			"returnType" => $this->convertAstNode($node->children['returnType']),
			"stmts" => $this->convertAstNodeArray($node->children['stmts'])
		];
		return new \PhpParser\Node\Expr\Closure($subNodes);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\ClosureUse
	 */
	private function closureVar(Node $node) {
		return new \PhpParser\Node\Expr\ClosureUse(
			$node->children['name'],
			$node->flags === \ast\flags\PARAM_REF
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\BinaryOp\Coalesce
	 */
	private function coalesce(Node $node) {
		return new \PhpParser\Node\Expr\BinaryOp\Coalesce(
			$this->convertAstNode($node->children['left']),
			$this->convertAstNode($node->children['right'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Ternary
	 */
	private function conditional(Node $node) {
		return new \PhpParser\Node\Expr\Ternary(
			$this->convertAstNode($node->children['cond']),
			$this->convertAstNode($node->children['true']),
			$this->convertAstNode($node->children['false'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\ConstFetch
	 */
	private function constStmt(Node $node) {
		return new \PhpParser\Node\Expr\ConstFetch($this->convertAstNode($node->children['name']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Const_
	 */
	private function constDecl(Node $node) {
		return new \PhpParser\Node\Stmt\Const_($this->convertAstNodeArray($node->children));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Const_
	 */
	private function constElem(Node $node) {
		return new \PhpParser\Node\Const_($node->children['name'], $this->convertAstNode($node->children['value']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Continue_
	 */
	private function continueStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Continue_($this->convertAstNode($node->children['depth']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Declare_
	 */
	private function declareStmt(Node $node) {
		$declares = [];
		foreach ($node->children['declares']->children as $declare) {
			$declares[] = new \PhpParser\Node\Stmt\DeclareDeclare($declare->children['name'], $this->convertAstNode($declare->children['value']), ["startLine" => $node->lineno]);
		}
		return new \PhpParser\Node\Stmt\Declare_($declares, $this->convertAstNode($node->children['stmts']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\ArrayDimFetch
	 */
	private function dim(Node $node) {
		return new \PhpParser\Node\Expr\ArrayDimFetch(
			$this->convertAstNode($node->children['expr']),
			$this->convertAstNode($node->children['dim'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \BambooHR\Guardrail\DoWhileStatement
	 */
	private function doWhile(Node $node) {
		return new \BambooHR\Guardrail\DoWhileStatement(
			$this->convertAstNode($node->children['cond']),
			$this->convertAstNode($node->children['stmts'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Echo_
	 */
	private function echoStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Echo_($this->convertAstNodeArray($node->children));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Empty_
	 */
	private function emptyExpr(Node $node) {
		return new \PhpParser\Node\Expr\Empty_($this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Scalar\Encapsed
	 */
	private function encapsList(Node $node) {
		return new \PhpParser\Node\Scalar\Encapsed(
			$this->encapsedFromChildren($node)
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Exit_
	 */
	private function exitExpr(Node $node) {
		return new \PhpParser\Node\Expr\Exit_($this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\For_
	 */
	private function forStmt(Node $node) {
		$subNodes['init'] = $this->convertAstNode($node->children['init']);
		$subNodes['cond'] = $this->convertAstNode($node->children['cond']);
		$subNodes['loop'] = $this->convertAstNode($node->children['loop']);
		$subNodes['stmts'] = $this->convertAstNode($node->children['stmts']);
		return new \PhpParser\Node\Stmt\For_($subNodes);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Foreach_
	 */
	private function foreachStmt(Node $node) {
		$subNodes = ['stmts' => $this->convertAstNode($node->children['stmts']), 'byRef' => false];
		if (isset($node->children['key'])) {
			$subNodes['keyVar'] = $this->convertAstNode($node->children['key']);
		}

		$value = $node->children['value'];
		if ($value->kind == \ast\AST_REF) {
			$value = $value->children['var'];
			$subNodes['byRef'] = true;
		}

		return new \PhpParser\Node\Stmt\Foreach_(
			$this->convertAstNode($node->children['expr']),
			$this->convertAstNode($value),
			$subNodes,
			['startLine' => $node->lineno]
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Function_
	 */
	private function funcDecl(Node $node) {
		$subNodes = [
			"stmts" => $this->includeMethodBodies ? $this->convertAstNodeArray($node->children['stmts']) : [],
			"byRef" => $node->flags === \ast\flags\RETURNS_REF,
			"params" => $this->convertAstNode($node->children['params']),
			"returnType" => $this->convertAstNode($node->children["returnType"]),
		];
		return new \PhpParser\Node\Stmt\Function_($this->getNodeName($node), $subNodes);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Global_
	 */
	private function globalStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Global_([$this->convertAstNode($node->children['var'])]);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Goto_
	 */
	private function gotoStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Goto_($node->children['label']);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\HaltCompiler
	 */
	private function haltCompiler(Node $node) {
		return new \PhpParser\Node\Stmt\HaltCompiler('');
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\If_
	 */
	private function ifStmt(Node $node) {

		$childCount = count($node->children);
		$elseIf = [];
		$else = null;

		for ($index = 1; $index < $childCount; ++$index) {
			if ($node->children[$index]->children['cond'] !== null) {
				$elseIf[] = new \PhpParser\Node\Stmt\ElseIf_(
					$this->convertAstNode($node->children[$index]->children['cond']),
					$this->convertAstNode($node->children[$index]->children['stmts']),
					["startLine" => $node->children[$index]->lineno]
				);
			} else {
				$else = new \PhpParser\Node\Stmt\Else_($this->convertAstNode($node->children[$index]->children['stmts']));
			}
		}

		$subNodes = [
			"stmts" => $this->convertAstNode($node->children[0]->children['stmts']),
			"elseifs" => $elseIf,
			"else" => $else
		];

		return new \PhpParser\Node\Stmt\If_($this->convertAstNode($node->children[0]->children['cond']), $subNodes);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Eval_|\PhpParser\Node\Expr\Include_
	 */
	private function includeOrEval(Node $node) {
		$arg = $this->convertAstNode($node->children['expr']);

		switch ($node->flags) {
			case \ast\flags\EXEC_INCLUDE:
				return new \PhpParser\Node\Expr\Include_($arg, \PhpParser\Node\Expr\Include_::TYPE_INCLUDE);
			case \ast\flags\EXEC_INCLUDE_ONCE:
				return new \PhpParser\Node\Expr\Include_($arg, \PhpParser\Node\Expr\Include_::TYPE_INCLUDE_ONCE);
			case \ast\flags\EXEC_REQUIRE:
				return new \PhpParser\Node\Expr\Include_($arg, \PhpParser\Node\Expr\Include_::TYPE_REQUIRE);
			case \ast\flags\EXEC_REQUIRE_ONCE:
				return new \PhpParser\Node\Expr\Include_($arg, \PhpParser\Node\Expr\Include_::TYPE_REQUIRE_ONCE);
			case \ast\flags\EXEC_EVAL:
				return new \PhpParser\Node\Expr\Eval_($arg);
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_INCLUDE_OR_EVAL found.");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Instanceof_
	 */
	private function instanceofExpr(Node $node) {
		return new \PhpParser\Node\Expr\Instanceof_(
			$this->convertAstNode($node->children['expr']),
			$this->convertAstNode($node->children['class'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Isset_
	 */
	private function issetExpr(Node $node) {
		return new \PhpParser\Node\Expr\Isset_(is_array($node->children['var']) ? $this->convertAstNodeArray($node->children['var']) : [$this->convertAstNode($node->children['var'])]);
	}

	/**
	 * @param Node $node
	 * @return \PhpParser\Node\Stmt\Label
	 */
	private function label(Node $node) {
		return new \PhpParser\Node\Stmt\Label($node->children['name']);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\List_
	 */
	private function listExpr(Node $node) {
		return new \PhpParser\Node\Expr\List_(array_map([$this, 'reverseAST'], $node->children));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Scalar\MagicConst
	 */
	private function magicConst(Node $node) {
		switch ($node->flags) {
			case \ast\flags\MAGIC_LINE:
				return new \PhpParser\Node\Scalar\MagicConst\Line();
			case \ast\flags\MAGIC_FILE:
				return new \PhpParser\Node\Scalar\MagicConst\File();
			case \ast\flags\MAGIC_DIR:
				return new \PhpParser\Node\Scalar\MagicConst\Dir();
			case \ast\flags\MAGIC_TRAIT:
				return new \PhpParser\Node\Scalar\MagicConst\Trait_();
			case \ast\flags\MAGIC_METHOD:
				return new \PhpParser\Node\Scalar\MagicConst\Method();
			case \ast\flags\MAGIC_FUNCTION:
				return new \PhpParser\Node\Scalar\MagicConst\Function_();
			case \ast\flags\MAGIC_NAMESPACE:
				return new \PhpParser\Node\Scalar\MagicConst\Namespace_();
			case \ast\flags\MAGIC_CLASS:
				return new \PhpParser\Node\Scalar\MagicConst\Class_();
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for T_MAGIC_CONST found.");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\ClassMethod
	 */
	private function method(Node $node) {
		$flags = 0;
		if ($node->flags & \ast\flags\MODIFIER_PUBLIC) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC;
		}

		if ($node->flags & \ast\flags\MODIFIER_PRIVATE) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE;
		}

		if ($node->flags & \ast\flags\MODIFIER_PROTECTED) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED;
		}

		if ($node->flags & \ast\flags\MODIFIER_ABSTRACT) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_ABSTRACT;
		}

		if ($node->flags & \ast\flags\MODIFIER_STATIC) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_STATIC;
		}

		if ($node->flags & \ast\flags\MODIFIER_FINAL) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_FINAL;
		}

		$subNodes = [
			"byRef" => boolval(\ast\flags\RETURNS_REF & $node->flags),
			"flags" => $flags,
			"params" => $this->convertAstNode($node->children['params']),
			"stmts" => $this->includeMethodBodies ? $this->convertAstNode($node->children['stmts']) : [],
			"returnType" => $this->convertAstNode($node->children['returnType'])
		];
		return new \PhpParser\Node\Stmt\ClassMethod(
			$this->getNodeName($node),
			$subNodes
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\MethodCall
	 */
	private function methodCall(Node $node) {
		return new \PhpParser\Node\Expr\MethodCall(
			$this->convertAstNode($node->children['expr']),
			is_string($node->children['method']) ? $node->children['method'] : $this->convertAstNode($node->children['method']),
			$this->convertAstNode($node->children['args'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Name
	 */
	private function name(Node $node) {
		switch ($node->flags) {
			case \ast\flags\NAME_FQ:
				return new \PhpParser\Node\Name\FullyQualified($node->children['name']);
			case \ast\flags\NAME_NOT_FQ:
				return new \PhpParser\Node\Name($node->children['name']);
			case \ast\flags\NAME_RELATIVE:
				return new \PhpParser\Node\Name\Relative($node->children['name']);
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_PARAM found.\n");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Namespace_
	 */
	private function namespaceStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Namespace_(
			$node->children['name'] ? new \PhpParser\Node\Name($node->children['name']) : null,
			$this->convertAstNodeArray($node->children['stmts'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\New_
	 */
	private function newExpr(Node $node) {
		return new \PhpParser\Node\Expr\New_(
			$this->convertAstNode($node->children['class']),
			$this->convertAstNode($node->children['args']),
			["startLine" => $node->lineno]
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\NullableType
	 */
	private function nullableType(Node $node) {
		return new \PhpParser\Node\NullableType($this->convertAstNode($node->children['type']));
	}

	/**
	 * @param mixed $scalar The scalar value to store.
	 * @param int   $lineNo The line number to associate with the token.
	 * @return \PhpParser\Node\Scalar
	 */
	private function scalarToNode($scalar, $lineNo) {
		switch (gettype($scalar)) {
			case "integer":
				return new \PhpParser\Node\Scalar\LNumber($scalar, ["startLine" => $lineNo]);
			case "string":
				return new \PhpParser\Node\Scalar\String_($scalar, ["startLine" => $lineNo]);
			case "double":
				return new \PhpParser\Node\Scalar\DNumber($scalar, ["startLine" => $lineNo]);
			default:
				throw new PhpAstToPhpParserException("Unknown scalar type: " . gettype($scalar));
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Param
	 */
	private function param(Node $node) {
		$node2 = new \PhpParser\Node\Param(
			$node->children['name'],
			is_scalar($node->children['default']) ? $this->scalarToNode($node->children['default'], $node->lineno) : $this->convertAstNode($node->children['default']),
			$this->convertAstNode($node->children['type']),
			boolval($node->flags & \ast\flags\PARAM_REF),
			boolval($node->flags & \ast\flags\PARAM_VARIADIC),
			["startLine" => $node->lineno]
		);
		return $node2;
	}

	/**
	 * @param Node $node -
	 * @return array
	 */
	private function paramList(Node $node) {
		return $this->convertAstNodeArray($node->children);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\PostDec
	 */
	private function postDec(Node $node) {
		return new \PhpParser\Node\Expr\PostDec($this->convertAstNode($node->children['var']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\PostInc
	 */
	private function postInc(Node $node) {
		return new \PhpParser\Node\Expr\PostInc($this->convertAstNode($node->children['var']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\PreDec
	 */
	private function preDec(Node $node) {
		return new \PhpParser\Node\Expr\PreDec($this->convertAstNode($node->children['var']));
	}

	/**
	 * @param Node $node
	 * @return \PhpParser\Node\Expr\PreInc
	 */
	private function preInc(Node $node) {
		return new \PhpParser\Node\Expr\PreInc($this->convertAstNode($node->children['var']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Print_
	 */
	private function printExpr(Node $node) {
		return new \PhpParser\Node\Expr\Print_($this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\PropertyFetch
	 * @throws PhpAstToPhpParserException
	 */
	private function prop(Node $node) {
		return new \PhpParser\Node\Expr\PropertyFetch(
			$this->convertAstNode($node->children['expr']),
			is_string($node->children['prop']) ? $node->children['prop'] : $this->convertAstNode($node->children['prop'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Property
	 */
	private function propDecl(Node $node) {

		$flags = 0;
		if ($node->flags & \ast\flags\MODIFIER_STATIC) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_STATIC;
		}
		if ($node->flags & \ast\flags\MODIFIER_PUBLIC) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC;
		}
		if ($node->flags & \ast\flags\MODIFIER_PRIVATE) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE;
		}
		if ($node->flags & \ast\flags\MODIFIER_PROTECTED) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED;
		}
		if ($node->flags & \ast\flags\MODIFIER_PUBLIC) {
			$flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC;
		}

		return new \PhpParser\Node\Stmt\Property($flags, $this->convertAstNodeArray($node->children));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\PropertyProperty
	 */
	private function propElem(Node $node) {
		return new \PhpParser\Node\Stmt\PropertyProperty($node->children['name'], $this->convertAstNode($node->children['default']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Return_
	 */
	private function returnExpr(Node $node) {
		return new \PhpParser\Node\Stmt\Return_(
			$this->convertAstNode($node->children['expr'])
		);
	}

	/**
	 * @param Node $node -
	 * @return array
	 */
	private function encapsedFromChildren(Node $node) {
		$children = [];
		foreach ($node->children as $child) {
			if (is_scalar($child)) {
				$child = new \PhpParser\Node\Scalar\EncapsedStringPart($child);
			} else {
				$child = $this->convertAstNode($child);
			}
			$children[] = $child;
		}
		return $children;
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\ShellExec
	 */
	private function shellExec(Node $node) {
		$expr = $node->children['expr'];
		if (is_string($expr)) {
			$expr = [new \PhpParser\Node\Scalar\EncapsedStringPart($expr)];
		} else {
			$expr = $this->encapsedFromChildren($expr);
		}
		return new \PhpParser\Node\Expr\ShellExec($expr);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Static_
	 */
	private function staticStmt(Node $node) {
		$staticVar = new \PhpParser\Node\Stmt\StaticVar(
			$node->children['var']->children['name'],
			$this->convertAstNode($node->children['default'])
		);
		return new \PhpParser\Node\Stmt\Static_([$staticVar]);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\StaticCall
	 */
	private function staticCall(Node $node) {
		return new \PhpParser\Node\Expr\StaticCall(
			is_string($node->children['class']) ? new \PhpParser\Node\Name($node->children['class']) : $this->convertAstNode($node->children['class']),
			is_string($node->children['method']) ? $node->children['method'] : $this->convertAstNode($node->children['method']),
			$this->convertAstNode($node->children['args'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\StaticPropertyFetch|\PhpParser\Node\Name
	 */
	private function staticProp(Node $node) {
		if (is_string($node->children['class'])) {
			$class = $node->children['class'];
			return new \PhpParser\Node\Name($class);
		} else {
			$class = $this->convertAstNode($node->children['class']);
		}
		return new \PhpParser\Node\Expr\StaticPropertyFetch(
			$class,
			is_string($node->children['prop']) ? $node->children['prop'] : $this->convertAstNode($node->children['prop'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node[]
	 */
	private function stmtList(Node $node) {
		return $this->convertAstNodeArray($node->children);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Switch_
	 */
	private function switchStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Switch_(
			$this->convertAstNode($node->children['cond']),
			$this->convertAstNode($node->children['stmts'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Case_
	 */
	private function switchCase(Node $node) {
		return new \PhpParser\Node\Stmt\Case_(
			$this->convertAstNode($node->children['cond']),
			$this->convertAstNodeArray($node->children['stmts'])
		);
	}

	/**
	 * @param Node $node -
	 * @return array
	 */
	private function switchList(Node $node) {
		return $this->convertAstNodeArray($node->children);
	}


	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Throw_
	 */
	private function throwStmt(Node $node) {
		return new \PhpParser\Node\Stmt\Throw_($this->convertAstNode($node->children['expr']));
	}

	/**
	 * @param Node $node -
	 * @return array
	 */
	private function traitAdaptations(Node $node) {
		return $this->convertAstNodeArray($node->children);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\TraitUseAdaptation\Precedence
	 */
	private function traitPrecedence(Node $node) {
		return new \PhpParser\Node\Stmt\TraitUseAdaptation\Precedence(
			$this->convertAstNode($node->children['class']),
			$this->convertAstNode($node->children['method']),
			$this->convertAstNode($node->children['insteadof']),
			["startLine" => $node->lineno]
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\TryCatch
	 */
	private function tryStmt(Node $node) {
		return new \PhpParser\Node\Stmt\TryCatch(
			$this->convertAstNode($node->children['try']),
			$this->convertAstNode($node->children['catches']),
			$node->children['finally'] ? new \PhpParser\Node\Stmt\Finally_($this->convertAstNode($node->children['finally']), ["startLine" => $node->lineno]) : null,
			["startLine" => $node->lineno]
		);
	}


	/**
	 * @param Node $node -
	 * @return string
	 */
	private function type(Node $node) {
		switch ($node->flags) {
			case \ast\flags\TYPE_ARRAY:
				return 'array';
			case \ast\flags\TYPE_CALLABLE:
				return 'callable';
			case \ast\flags\TYPE_LONG:
				return 'int';
			case \ast\flags\TYPE_STRING:
				return 'string';
			case \ast\flags\TYPE_BOOL:
				return 'bool';
			case \ast\flags\TYPE_ITERABLE:
				return 'iterable';
			case \ast\flags\TYPE_DOUBLE:
				return 'float';
			case \ast\flags\TYPE_VOID:
				return 'void';
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_TYPE found.");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr
	 */
	private function unaryOp(Node $node) {
		$expr = $this->convertAstNode($node->children['expr']);

		switch ($node->flags) {
			case \ast\flags\UNARY_BOOL_NOT:
				return new \PhpParser\Node\Expr\BooleanNot($expr);
			case \ast\flags\UNARY_BITWISE_NOT:
				return new \PhpParser\Node\Expr\BitwiseNot($expr);
			case \ast\flags\UNARY_SILENCE:
				return new \PhpParser\Node\Expr\ErrorSuppress($expr);
			case \ast\flags\UNARY_PLUS:
				return new \PhpParser\Node\Expr\UnaryPlus($expr);
			case \ast\flags\UNARY_MINUS:
				return new \PhpParser\Node\Expr\UnaryMinus($expr);
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_UNARY_OP found.");
		}
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Unset_
	 */
	private function unsetExpr(Node $node) {
		return new \PhpParser\Node\Stmt\Unset_(is_array($node->children['var']) ? $this->convertAstNodeArray($node->children['var']) : [$this->convertAstNode($node->children['var'])]);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\GroupUse
	 */
	private function groupUse(Node $node) {
		static $map = [
			\ast\flags\USE_CONST => \PhpParser\Node\Stmt\Use_::TYPE_CONSTANT,
			\ast\flags\USE_FUNCTION => \PhpParser\Node\Stmt\Use_::TYPE_FUNCTION,
			\ast\flags\USE_NORMAL => \PhpParser\Node\Stmt\Use_::TYPE_NORMAL,
			0 => \PhpParser\Node\Stmt\Use_::TYPE_UNKNOWN
		];
		return new \PhpParser\Node\Stmt\GroupUse(
			$node->children['prefix'],
			$this->convertAstNode($node->children['uses']),
			$map[$node->flags]
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\Use_
	 */
	private function useStmt(Node $node) {
		static $map = [
			\ast\flags\USE_CONST => \PhpParser\Node\Stmt\Use_::TYPE_CONSTANT,
			\ast\flags\USE_FUNCTION => \PhpParser\Node\Stmt\Use_::TYPE_FUNCTION,
			\ast\flags\USE_NORMAL => \PhpParser\Node\Stmt\Use_::TYPE_NORMAL,
			0 => \PhpParser\Node\Stmt\Use_::TYPE_UNKNOWN
		];
		return new \PhpParser\Node\Stmt\Use_($this->convertAstNodeArray($node->children), $map[$node->flags]);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\UseUse
	 */
	private function useElem(Node $node) {
		return new \PhpParser\Node\Stmt\UseUse(new \PhpParser\Node\Name($node->children['name']), $node->children['alias']);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\TraitUseAdaptation\Alias
	 */
	private function traitAlias(Node $node) {
		$modifier = 0;
		switch ($node->flags) {
			case \ast\flags\MODIFIER_PUBLIC:
				$modifier = \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC;
				break;
			case \ast\flags\MODIFIER_PROTECTED:
				$modifier = \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED;
				break;
			case \ast\flags\MODIFIER_PRIVATE:
				$modifier = \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE;
				break;
			case 0:
				//Do nothing
				break;
			default:
				throw new PhpAstToPhpParserException("Unknown flag ({$node->flags}) for AST_TRAIT_ALIAS found.");
		}
		return new \PhpParser\Node\Stmt\TraitUseAdaptation\Alias(
			$this->convertAstNode($node->children['method']->children['class']),
			$node->children['method']->children['method'],
			$modifier,
			$node->children['alias'] ?? null
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\TraitUse
	 */
	private function useTrait(Node $node) {
		$traits = $this->convertAstNodeArray($node->children['traits']->children);
		$adaptations = [];
		if (isset($node->children['adaptations']) && is_array($node->children['adaptations']->children)) {
			$adaptations = $this->convertAstNodeArray($node->children['adaptations']->children);
		}
		return new \PhpParser\Node\Stmt\TraitUse(
			$traits,
			$adaptations
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Variable
	 */
	private function variable(Node $node) {
		return new \PhpParser\Node\Expr\Variable(is_string($node->children['name']) ? $node->children['name'] : $this->convertAstNode($node->children['name']));
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Stmt\While_
	 */
	private function whileStmt(Node $node) {
		return new \PhpParser\Node\Stmt\While_(
			$this->convertAstNode($node->children['cond']),
			$this->convertAstNodeArray($node->children['stmts'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\Yield_
	 */
	private function yieldStmt(Node $node) {
		return new \PhpParser\Node\Expr\Yield_(
			$this->convertAstNode($node->children['value']),
			$this->convertAstNode($node->children['key'])
		);
	}

	/**
	 * @param Node $node -
	 * @return \PhpParser\Node\Expr\YieldFrom
	 */
	private function yieldFrom(Node $node) {
		return new \PhpParser\Node\Expr\YieldFrom($this->convertAstNode($node->children['expr']));
	}
}

// thrown whenever an unexpected ast\Node is discovered.
class PhpAstToPhpParserException extends \Exception {
}