<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Evaluators\Expression\Variable;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

class FunctionLike implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface
{

	function getInstanceType(): array
	{
		return [Closure::class, ArrowFunction::class, Node\Stmt\Function_::class, Node\Stmt\ClassMethod::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		self::handleEnterFunctionLike($node, $scopeStack);
		/** @var Node\FunctionLike $func */
		$func = $node;
		$this->updateFunctionEmit($func,  $scopeStack,"push");
	}


	static function handleEnterFunctionLike(Node $node, ScopeStack $scopeStack): void
	{
		/** @var Node\FunctionLike $func */
		$func = $node;
		$isStatic = true;
		if ($func instanceof ClassMethod) {
			$isStatic = $func->isStatic();
		} else if ($func instanceof Closure) {
			$isStatic = $func->static;
		} else if ($func instanceof Node\Expr\ArrowFunction) {
			$isStatic = $func->static;
		}

		$scope = new Scope\Scope($isStatic, false, $scopeStack->getCurrentScope()->isStrict, $func);

		$func->setAttribute('function-scope', $scope);
		foreach ($func->getParams() as $param) {
			//echo "  Param ".$param->var->name." ". $param->type. " ". ($param->default==NULL ? "Not null" : "default"). " ".($param->variadic ? "variadic" : "")."\n";
			if ($param->variadic) {
				$scope->setVarType(strval($param->var->name), TypeComparer::identifierFromName("array"), $param->getLine());
			} else {
				$scope->setVarType(strval($param->var->name), $param->type, $param->getLine());
			}
			$scope->setVarWritten($param->var->name, $func->getLine());
			$scope->setVarUsed(strval($param->var->name)); // It's ok to leave a parameter unused, so we just mark it used.
		}
		if ($func instanceof Node\Expr\ArrowFunction) {
			// Scan the arrow function for all variables and auto import them into the scope.
			$variables = self::getAllReferencedVariables([$func->expr]);
			foreach($variables as $varName) {
				if ($scopeStack->getVarExists($varName)) {
					$ob = $scopeStack->getVarObject($varName);
					$scope->setVarType($varName, $ob->type, $ob->modifiedLine);
				}
			}
		}
		if ($func instanceof Closure) {
			foreach ($func->uses as $variable) {

				// We don't track variables in global scope, so we'll have to assume those are ok.
				if (!$scopeStack->getVarExists($variable->var->name)) {
					if (!$scopeStack->isGlobal()) {
						$fileName=$scopeStack->getCurrentFile();
						$scopeStack->getOutput()->emitError(__CLASS__, $fileName, $variable->getLine(), ErrorConstants::TYPE_UNKNOWN_VARIABLE, "Attempt to use unknown variable \$" . $variable->var->name . " in uses() clause");
					}
				} else {
					$type = $scopeStack->getVarType($variable->var->name);
					if ($variable->byRef) {
						// This is kind of fun, it's passed by reference so we literally reference the exact same
						// scope variable object in the new scope.  If it changes in either scope, it effects the others.
						$scope->setVarReference($variable->var->name, $scopeStack->getVarObject($variable->var->name));
					} else {
						$scope->setVarType($variable->var->name, $type, $variable->var->getLine());
						$scopeStack->setVarUsed($variable->var->name);
					}
				}
			}
		}
		$scopeStack->pushScope($scope);
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void
	{
		self::handleUnusedVars($scopeStack);
		$closureScope = $scopeStack->popScope();


		assert($node instanceof \PhpParser\Node\FunctionLike);
		$this->updateFunctionEmit($node, $scopeStack,"pop");
	}

	static function getAllReferencedVariables(array $nodes) {
		$variables = [];
		ForEachNode::run($nodes, function($node) use (&$variables) {
			if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
				$variables[$node->name]=true;
			}
		});
		return array_keys($variables);
	}

	static function handleUnusedVars(ScopeStack $scopeStack) {
		$unusedVars = $scopeStack->getUnusedVars();

		if (count($unusedVars) > 0) {
			foreach ($unusedVars as $varName => $lineNumber) {
				if(!str_contains($varName,"-")) {
					$scopeStack->getOutput()->emitError(__CLASS__, $scopeStack->getCurrentFile(), $lineNumber, ErrorConstants::TYPE_UNUSED_VARIABLE, '$' . $varName . " is assigned but never referenced");
				}
			}
		}
	}

	/**
	 * updateFunctionEmit
	 *
	 * @param \PhpParser\Node\FunctionLike $func      Instance of FunctionLike
	 * @param string       $pushOrPop Push | Pop
	 *
	 * @return void
	 */
	public function updateFunctionEmit(Node\FunctionLike $func, ScopeStack $scopeStack, $pushOrPop) {
		$docBlock = $func->getDocComment();
		if (!empty($docBlock)) {
			$docBlock = trim($docBlock);
			$ignoreList = [];

			if (preg_match_all("/@guardrail-ignore ([A-Za-z. ,]*)/", $docBlock, $ignoreList)) {
				foreach ($ignoreList[1] as $ignoreListEntry) {
					$toIgnore = explode(",", $ignoreListEntry);
					foreach ($toIgnore as $type) {
						$type = trim($type);
						if (!empty($type)) {
							if ($pushOrPop == "push") {
								$scopeStack->getOutput()->silenceType($type);
							} else {
								$scopeStack->getOutput()->resumeType($type);
							}
						}
					}
				}
			}
		}
	}
}