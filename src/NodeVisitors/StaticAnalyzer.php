<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassMethod as AbstractClassMethod;
use BambooHR\Guardrail\Checks\AccessingSuperGlobalsCheck;
use BambooHR\Guardrail\Checks\BackTickOperatorCheck;
use BambooHR\Guardrail\Checks\BreakCheck;
use BambooHR\Guardrail\Checks\CatchCheck;
use BambooHR\Guardrail\Checks\ClassConstantCheck;
use BambooHR\Guardrail\Checks\ConditionalAssignmentCheck;
use BambooHR\Guardrail\Checks\ConstructorCheck;
use BambooHR\Guardrail\Checks\CyclomaticComplexityCheck;
use BambooHR\Guardrail\Checks\DefinedConstantCheck;
use BambooHR\Guardrail\Checks\DocBlockTypesCheck;
use BambooHR\Guardrail\Checks\FunctionCallCheck;
use BambooHR\Guardrail\Checks\GotoCheck;
use BambooHR\Guardrail\Checks\InstanceOfCheck;
use BambooHR\Guardrail\Checks\InstantiationCheck;
use BambooHR\Guardrail\Checks\InterfaceCheck;
use BambooHR\Guardrail\Checks\MethodCall;
use BambooHR\Guardrail\Checks\ParamTypesCheck;
use BambooHR\Guardrail\Checks\PropertyFetchCheck;
use BambooHR\Guardrail\Checks\Psr4Check;
use BambooHR\Guardrail\Checks\ReturnCheck;
use BambooHR\Guardrail\Checks\StaticCallCheck;
use BambooHR\Guardrail\Checks\StaticPropertyFetchCheck;
use BambooHR\Guardrail\Checks\SwitchCheck;
use BambooHR\Guardrail\Checks\UndefinedVariableCheck;
use BambooHR\Guardrail\Checks\UnreachableCodeCheck;
use BambooHR\Guardrail\Config;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Context;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeTraverserInterface;
use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Abstractions\ClassAbstraction as AbstractionClass;
use BambooHR\Guardrail\Abstractions\ReflectedClassMethod;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\NodeVisitorAbstract;
use BambooHR\Guardrail\Checks\ErrorConstants;

/**
 * Class StaticAnalyzer
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class StaticAnalyzer extends NodeVisitorAbstract {

	/** @var  SymbolTable */
	private $index;

	/** @var string */
	private $file;

	/** @var \BambooHR\Guardrail\Checks\BaseCheck[] */
	private $checks = [];

	/** @var Class_[] */
	private $classStack = [];
	/** @var Scope[] */
	private $scopeStack = [];

	/** @var TypeInferrer */
	private $typeInferrer;

	/** @var OutputInterface */
	private $output;

	private $timings = [];

	/**
	 * @return array
	 */
	function getTimings() {
		return $this->timings;
	}

	/**
	 * StaticAnalyzer constructor.
	 *
	 * @param string          $basePath The base path
	 * @param string          $index    The index
	 * @param OutputInterface $output   Instance if OutputInterface
	 * @param Config          $config   The config
	 */
	function __construct($basePath, $index, OutputInterface $output, $config) {
		$this->index = $index;
		$this->scopeStack = [new Scope(true, true)];
		$this->typeInferrer = new TypeInferrer($index);
		$this->output = $output;

		/** @var \BambooHR\Guardrail\Checks\BaseCheck[] $checkers */
		$checkers = [
			new DocBlockTypesCheck($this->index, $output),
			new UndefinedVariableCheck($this->index, $output),
			new DefinedConstantCheck($this->index, $output),
			new BackTickOperatorCheck($this->index, $output),
			new PropertyFetchCheck($this->index, $output),
			new InterfaceCheck($this->index, $output),
			new ParamTypesCheck($this->index, $output),
			new StaticCallCheck($this->index, $output),
			new InstantiationCheck($this->index, $output),
			new InstanceOfCheck($this->index, $output),
			new CatchCheck($this->index, $output),
			new ClassConstantCheck($this->index, $output),
			new FunctionCallCheck($this->index, $output),
			new MethodCall($this->index, $output),
			new SwitchCheck($this->index, $output),
			new BreakCheck($this->index, $output),
			new ConstructorCheck($this->index, $output),
			new GotoCheck($this->index, $output),
			new ReturnCheck($this->index, $output),
			new StaticPropertyFetchCheck($this->index, $output),
			new AccessingSuperGlobalsCheck($this->index, $output),
			new UnreachableCodeCheck($this->index, $output),
			new Psr4Check($this->index, $output),
			new CyclomaticComplexityCheck($this->index, $output),
			new ConditionalAssignmentCheck($this->index, $output)
		];

		$checkers = array_merge( $checkers, $config->getPlugins($this->index, $output) );

		foreach ($checkers as $checker) {
			foreach ($checker->getCheckNodeTypes() as $nodeType) {
				if (!isset($this->checks[$nodeType])) {
					$this->checks[$nodeType] = [$checker];
				} else {
					$this->checks[$nodeType][] = $checker;
				}
			}
		}
	}

	/**
	 * setFile
	 *
	 * @param string $name The name
	 *
	 * @return void
	 */
	public function setFile($name) {
		$this->file = $name;
		$this->scopeStack = [new Scope(true, true)];
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of the node
	 *
	 * @return null
	 */
	public function enterNode(Node $node) {
		$class = get_class($node);
		if ($node instanceof Trait_) {
			return NodeTraverserInterface::DONT_TRAVERSE_CHILDREN;
		}
		if (!($node instanceof FunctionLike)) {
			$vars = $node->getAttribute("namespacedInlineVar");
			if (is_array($vars)) {
				foreach ($vars as $varName => $varType) {
					end($this->scopeStack)->setVarType($varName, $varType, $node->getLine());
				}
			}
		}
		if ($node instanceof Class_ || $node instanceof Trait_) {
			array_push($this->classStack, $node);
		}
		if (($node instanceof ClassMethod && count($this->classStack)>0) || $node instanceof Function_ || $node instanceof Closure) { // Typecast
			$this->pushFunctionScope($node);
		}
		if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignRef) {
			$this->handleAssignment($node);
		}
		if ($node instanceof Node\Stmt\StaticVar) {
			$this->setScopeExpression($node->name, $node->default, $node->getLine());
		}
		if ($node instanceof Node\Stmt\Catch_) {
			$this->setScopeType(strval($node->var), strval($node->type), $node->getLine());
			$this->setScopeUsed(strval($node->var));
		}
		if ($node instanceof Node\Stmt\Global_) {
			foreach ($node->vars as $var) {
				if ($var instanceof Variable && gettype($var->name) == "string") {
					$this->setScopeType(strval($var->name), Scope::MIXED_TYPE, $var->getLine());
				}
			}
		}
		if ($node instanceof Node\Expr\MethodCall) {
			if (gettype($node->name) == "string") {

				list($type) = $this->typeInferrer->inferType( end($this->classStack) ?: null, $node->var, end($this->scopeStack) );
				if ($type && $type[0] != "!") {
					$method = $this->index->getAbstractedMethod($type, $node->name);
					if ($method) {
						$this->processMethodCall($node, $method);
					}
				}
			}
		}
		if ($node instanceof Node\Expr\StaticCall) {
			if ($node->class instanceof Node\Name && gettype($node->name) == "string") {
				$method = $this->index->getAbstractedMethod( strval($node->class), strval($node->name));
				if ($method) {
					$this->processStaticCall($node, $method);
				}
			}
		}
		if ($node instanceof Node\Expr\FuncCall) {
			if ($node->name instanceof Node\Name) {
				if (strcasecmp($node->name, "assert") == 0 &&
					count($node->args) == 1
				) {
					$var = $node->args[0]->value;
					if (
						$var instanceof Instanceof_ &&
						$var->expr instanceof Variable &&
						gettype($var->expr->name) == "string" &&
						$var->class instanceof Node\Name
					) {
						end($this->scopeStack)->setVarType($var->expr->name, strval($var->class), $var->getLine());
						end($this->scopeStack)->setVarNull($var->expr->name, Scope::NULL_IMPOSSIBLE);
					}
				}

				$function = $this->index->getAbstractedFunction(strval($node->name));
				if ($function) {
					$params = $function->getParameters();
					$paramCount = count($params);
					foreach ($node->args as $index => $arg) {
						if ($arg->value instanceof Variable && gettype($arg->value->name) == "string" &&
							(
								(isset($params[$index]) && $params[$index]->isReference()) ||
								($index >= $paramCount && $paramCount > 0 && $params[$paramCount - 1]->isReference())
							)
						) {
							$this->setScopeType($arg->value->name, Scope::MIXED_TYPE, $arg->value->getLine());
						}
					}
				}
			}
		}

		if ($node instanceof Node\Stmt\Foreach_) {
			if ($node->keyVar instanceof Variable && gettype($node->keyVar->name) == "string") {
				$this->setScopeType(strval($node->keyVar->name), Scope::MIXED_TYPE, $node->keyVar->getLine());
			}
			if ($node->valueVar instanceof Variable && gettype($node->valueVar->name) == "string") {
				list($type) = $this->typeInferrer->inferType(end($this->classStack) ?: null, $node->expr, end($this->scopeStack));
				if (substr($type, -2) == "[]") {
					$type = substr($type, 0, -2);
				} else {
					$type = Scope::MIXED_TYPE;
				}
				$this->setScopeType(strval($node->valueVar->name), $type, $node->valueVar->getLine());
			} else if ($node->valueVar instanceof List_) {
				foreach ($node->valueVar->vars as $var) {
					if ($var instanceof Variable && gettype($var->name) == "string") {
						$this->setScopeType(strval($var->name), Scope::MIXED_TYPE, $var->getLine());
					}
				}
			}
		}
		if ($node instanceof ElseIf_) {
			// Pop the previous if's scope
			$last = array_pop($this->scopeStack);
			$this->pushIfScope($node, $last);
		}

		if ($node instanceof If_) {
			$this->pushIfScope($node);
		}

		if ($node instanceof Node\Stmt\Else_) {
			$last = array_pop($this->scopeStack); // Save the old scope so we can merge it later.
			$this->pushIfScope($node, $last);
		}

		if (isset($this->checks[$class])) {
			$last = microtime(true);
			foreach ($this->checks[$class] as $check) {
				$start = $last;
				$check->run($this->file, $node, end($this->classStack) ?: null, end($this->scopeStack) ?: null);
				$last = microtime(true);
				$name = get_class($check);
				$this->timings[$name] = (isset($this->timings[$name]) ? $this->timings[$name] : 0) + ($last - $start);
			}
		}
		return null;
	}

	/**
	 * pushIfScope
	 *
	 * @param Node  $node     Instance of Node
	 * @param Scope $previous The previous scope
	 *
	 * @return void
	 */
	function pushIfScope(Node $node, Scope $previous=null) {
		/** @var Scope $scope */
		$scope = end($this->scopeStack);
		$newScope = $scope->getScopeClone($previous);
		if (self::isCastableIf($node)) {
			$this->addCastedScope($node, $newScope);
		}
		array_push($this->scopeStack, $newScope);
//		echo "  New scope created, depth=".count($this->scopeStack)."\n";
	}

	/**
	 * addCastedScope
	 *
	 * When a node is of the form "if ($var instanceof ClassName)" (with no else clauses) then we can
	 * relax the scoping rules inside the if statement to allow a different set of methods that might not
	 * be normally visible.  This is primarily used for downcasting.
	 *
	 * "ClassName" inside the true clause.
	 *
	 * @param Node  $node     Instance of Node
	 * @param Scope $newScope Instance of Scope
	 * @guardrail-ignore Standard.Unknown.Property
	 *
	 * @return void
	 */
	public function addCastedScope(Node $node, Scope $newScope) {

		/** @var Instanceof_ $cond */
		$cond = $node->cond;

		if ($cond->expr instanceof Variable && gettype($cond->expr->name) == "string" && $cond->class instanceof Node\Name) {
			$newScope->setVarType($cond->expr->name, strval($cond->class), $cond->expr->getLine());
		}
	}

	/**
	 * updateFunctionEmit
	 *
	 * @param FunctionLike $func      Instance of FunctionLike
	 * @param string       $pushOrPop Push | Pop
	 *
	 * @return void
	 */
	public function updateFunctionEmit(FunctionLike $func, $pushOrPop) {

		$docBlock = trim($func->getDocComment());
		$ignoreList = [];

		if (preg_match_all("/@guardrail-ignore ([A-Za-z. ,]*)/", $docBlock, $ignoreList)) {
			foreach ($ignoreList[1] as $ignoreListEntry) {
				$toIgnore = explode(",", $ignoreListEntry);
				foreach ($toIgnore as $type) {
					$type = trim($type);
					if (!empty($type)) {
						if ($pushOrPop == "push") {
							$this->output->silenceType($type);
						} else {
							$this->output->resumeType($type);
						}
					}
				}
			}
		}
	}

	/**
	 * pushFunctionScope
	 *
	 * @param FunctionLike $func Instance of FunctionLike
	 *
	 * @return void
	 */
	public function pushFunctionScope(FunctionLike $func) {
		$isStatic = true;
		if ($func instanceof ClassMethod) {
			$isStatic = $func->isStatic();
		}
//		echo "Function: ".$func->name." depth=".(count($this->scopeStack)+1)."\n";
		$scope = new Scope( $isStatic, false, $func );
		foreach ($func->getParams() as $param) {
			//echo "  Param ".$param->name." ". $param->type. " ". ($param->default==NULL ? "Not null" : "default"). " ".($param->variadic ? "variadic" : "")."\n";
			if ($param->variadic) {
				// TODO: Track the type of a variadic array
				$scope->setVarType(strval($param->name), 'array', $param->getLine());
			} else {
				$scope->setVarType(strval($param->name), Scope::constFromName(strval($param->type)), $param->getLine());
				if ($param->type != null && $param->default == null) {
					$scope->setVarNull(strval($param->name), Scope::NULL_IMPOSSIBLE);
				}
				$scope->setVarUsed(strval($param->name));
			}
		}
		if ($func instanceof Closure) {
			$oldScope = end($this->scopeStack);
			foreach ($func->uses as $variable) {
				$type = $oldScope->getVarType($variable->var);
				$oldScope->setVarUsed($variable->var);
				if ($type == Scope::UNDEFINED) {
					$type = Scope::MIXED_TYPE;
				}
				$scope->setVarType($variable->var, $type, $variable->getLine());
			}
		}

		if ($func instanceof ClassMethod || $func instanceof Function_) {
			$this->updateFunctionEmit($func, "push");
		}
		array_push($this->scopeStack, $scope);
	}

	/**
	 * isCastableIf
	 *
	 * An if is castable if there are no elseifs and the expr is a simple "InstanceOf" expression.
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return bool
	 */
	static public function isCastableIf(Node $node) {
		return ($node instanceof If_ || $node instanceof ElseIf_) && $node->cond instanceof Instanceof_;
	}

	/**
	 * setScopeExpression
	 *
	 * @param string    $varName Variable name
	 * @param Node\Expr $expr    Expression name
	 * @param int       $line    line number
	 *
	 * @return void
	 */
	private function setScopeExpression($varName, $expr, $line ) {
		$scope = end($this->scopeStack);
		$class = end($this->classStack) ?: null;
		list($newType, $nullable) = $this->typeInferrer->inferType($class, $expr, $scope);
		$this->setScopeType($varName, $newType, $line);
		if ($nullable == Scope::NULL_POSSIBLE) {
			$scope->setVarNull($varName);
		}
	}



	/**
	 * setScopeType
	 *
	 * @param string $varName Variable name
	 * @param string $newType The new type
	 * @param int    $line    The line number
	 *
	 * @return void
	 */
	private function setScopeType($varName, $newType, $line) {
		$scope = end($this->scopeStack);
		$oldType = $scope->getVarType($varName);
		if ($oldType != $newType) {
			if ($oldType == Scope::UNDEFINED) {
				$scope->setVarType($varName, $newType, $line);
			} elseif ($newType == Scope::NULL_TYPE) {
				$scope->setVarNull($varName);
			} else {
				// The variable has been used with 2 different types.  Update it in the scope as a mixed type.
				$scope->setVarType($varName, Scope::MIXED_TYPE, $line);
			}
		}
	}

	/**
	 * setAllScopeUsed
	 *
	 * @return void
	 */
	private function setAllScopeUsed() {
		$scope = end($this->scopeStack);
		$scope->markAllVarsUsed();
	}

	/**
	 * setScopeUsed
	 *
	 * @param string $varName The name of the variable
	 *
	 * @return void
	 */
	private function setScopeUsed($varName) {
		$scope = end($this->scopeStack);
		$scope->setVarUsed($varName);
	}

	/**
	 * setScopeWritten
	 *
	 * @param string $varName    The name of the variable
	 * @param int    $lineNumber The line number
	 *
	 * @return void
	 */
	private function setScopeWritten($varName, $lineNumber) {
		$scope = end($this->scopeStack);
		$scope->setVarWritten($varName, $lineNumber);
	}


	/**
	 * handleAssignment
	 *
	 * Assignment can cause a new variable to come into scope.  We infer the type of the expression (if possible) and
	 * add an entry to the variable table for this scope.
	 *
	 * @param Node\Expr\Assign|Node\Expr\AssignRef $op Variable instances of different things
	 *
	 * @return void
	 */
	private function handleAssignment($op) {

		if ($op->var instanceof Node\Expr\Variable && gettype($op->var->name) == "string") {
			$overrides = $op->getAttribute('namespacedInlineVar');
			$op->var->setAttribute('assignment', true);

			$varName = strval($op->var->name);

			// If it's in overrides, then it was already set by a DocBlock @var
			if (!isset($overrides[$varName])) {
				$this->setScopeExpression($varName, $op->expr, $op->expr->getLine());
			}
		} else if ($op->var instanceof List_) {
			// We're not going to examine a potentially complex right side of the assignment, so just set all vars to mixed.
			foreach ($op->var->vars as $var) {
				if ($var && $var instanceof Variable && gettype($var->name) == "string") {
					$this->setScopeType(strval($var->name), Scope::MIXED_TYPE, $var->getLine());
				}
			}
		} else if ($op->var instanceof ArrayDimFetch) {
			$var = $op->var;
			while ($var instanceof ArrayDimFetch) {
				$var = $var->var;
			}
			if ($var instanceof Variable && gettype($var->name) == "string") {
				$varName = strval($var->name);
//				echo "  Set $varName\n";
				$this->setScopeType($varName, "array", $var->getLine());

			}
		}
	}

	/**
	 * leaveNode
	 *
	 * @param Node $node Instance of node
	 *
	 * @return null
	 */
	public function leaveNode(Node $node) {
		if ($node instanceof Node\Expr\Variable &&
			is_string($node->name) &&
			!$node->hasAttribute('assignment')
		) {
			$this->setScopeUsed($node->name);
		}
		if ($node instanceof Node\Expr\ClosureUse) {
			if (is_string($node->var)) {
				$this->setScopeUsed($node->var);
			}
		}

		if ($node instanceof Node\Expr\FuncCall) {
			if (
				$node->name instanceof Node\Name &&
				strcasecmp(strval($node->name), "get_defined_vars()") == 0
			) {
				$this->setAllScopeUsed();
			}
		}

		if ($node instanceof Node\Expr\Assign) {
			if (
				$node->var instanceof Node\Expr\Variable &&
				is_string($node->var->name)
			) {
				$this->setScopeWritten($node->var->name, $node->getLine());
			}
		}

		if ($node instanceof Node\Expr\AssignRef) {
			if (
				$node->var instanceof Node\Expr\Variable &&
				is_string($node->var->name)
			) {
				$this->setScopeWritten($node->var->name, $node->getLine());
			}
		}

		if ($node instanceof Class_) {
			array_pop($this->classStack);
		}
		if (($node instanceof ClassMethod && count($this->classStack)>0) || $node instanceof Function_ || $node instanceof Closure) {
			$scope = array_pop($this->scopeStack);
//			echo "Exit function ".$node->name." scope depth=".count($this->scopeStack)."\n";

			$unusedVars = $scope->getUnusedVars();

			if (count($unusedVars) > 0) {
				foreach ($unusedVars as $varName => $lineNumber) {
					$this->output->emitError(__CLASS__, $this->file, $lineNumber, ErrorConstants::TYPE_UNUSED_VARIABLE, '$' . $varName . " is assigned but never referenced");
				}
			}

			if ($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
				$this->updateFunctionEmit($node, "pop");
			}
		}

		if ($node instanceof ElseIf_) {
			$last = end($this->scopeStack);
			$last->mergePrevious();
		}

		if ($node instanceof Node\Stmt\Else_) {
			$last = end($this->scopeStack);
			$last->mergePrevious();
		}

		if ($node instanceof If_ ) {
			$last = array_pop($this->scopeStack);
			$next = end($this->scopeStack);
			$next->merge($last);
//			echo "  Exit if, depth = ".count($this->scopeStack)."\n";
		}
		return null;
	}

	/**
	 * processMethodCall
	 *
	 * @param Node\Expr\MethodCall                                                  $node   Instance of Node
	 * @param AbstractionClass|AbstractClassMethod|ReflectedClassMethod|null|string $method Instance of the Method (optional)
	 *
	 * @return void
	 */
	private function processMethodCall(Node\Expr\MethodCall $node, $method) {
		/** @var FunctionLikeParameter[] $params */
		$params = $method->getParameters();
		$paramCount = count($params);
		foreach ($node->args as $index => $arg) {
			if (
				(isset($params[$index]) && $params[$index]->isReference()) ||
				($index >= $paramCount && $paramCount > 0 && $params[$paramCount - 1]->isReference())
			) {
				if ($arg->value instanceof Variable && gettype($arg->value->name) == "string") {
					$this->setScopeType($arg->value->name, Scope::MIXED_TYPE, $arg->value->getLine());
				}
			}
		}
	}

	/**
	 * processStaticCall
	 *
	 * @param Node\Expr\StaticCall                                                  $node   Instance of Node
	 * @param AbstractionClass|AbstractClassMethod|ReflectedClassMethod|null|string $method Instance of the Method (optional)
	 *
	 * @return void
	 */
	private function processStaticCall(Node\Expr\StaticCall $node, $method) {
		/** @var FunctionLikeParameter[] $params */
		$params = $method->getParameters();
		$paramCount = count($params);
		foreach ($node->args as $index => $arg) {
			if (
				(isset($params[$index]) && $params[$index]->isReference()) ||
				($index >= $paramCount && $paramCount > 0 && $params[$paramCount - 1]->isReference())
			) {
				if ($arg->value instanceof Variable && gettype($arg->value->name) == "string") {
					$this->setScopeType($arg->value->name, Scope::MIXED_TYPE, $arg->value->getLine());
				}
			}
		}
	}
}
