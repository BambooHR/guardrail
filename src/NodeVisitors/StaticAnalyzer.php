<?php

namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2024, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Checks\AccessingSuperGlobalsCheck;
use BambooHR\Guardrail\Checks\BackTickOperatorCheck;
use BambooHR\Guardrail\Checks\BreakCheck;
use BambooHR\Guardrail\Checks\CatchCheck;
use BambooHR\Guardrail\Checks\ClassConstantCheck;
use BambooHR\Guardrail\Checks\ClassConstCheck;
use BambooHR\Guardrail\Checks\ClassMethodStringCheck;
use BambooHR\Guardrail\Checks\ConditionalAssignmentCheck;
use BambooHR\Guardrail\Checks\ConstructorCheck;
use BambooHR\Guardrail\Checks\CountableEmptinessCheck;
use BambooHR\Guardrail\Checks\CyclomaticComplexityCheck;
use BambooHR\Guardrail\Checks\DefinedConstantCheck;
use BambooHR\Guardrail\Checks\GlobalFunctionCheck;
use BambooHR\Guardrail\Checks\DocBlockTypesCheck;
use BambooHR\Guardrail\Checks\EnumCheck;
use BambooHR\Guardrail\Checks\FunctionCallCheck;
use BambooHR\Guardrail\Checks\GotoCheck;
use BambooHR\Guardrail\Checks\ImagickCheck;
use BambooHR\Guardrail\Checks\InstanceOfCheck;
use BambooHR\Guardrail\Checks\InstantiationCheck;
use BambooHR\Guardrail\Checks\InterfaceCheck;
use BambooHR\Guardrail\Checks\MethodCall;
use BambooHR\Guardrail\Checks\ParamTypesCheck;
use BambooHR\Guardrail\Checks\PropertyFetchCheck;
use BambooHR\Guardrail\Checks\PropertyStoreCheck;
use BambooHR\Guardrail\Checks\Psr4Check;
use BambooHR\Guardrail\Checks\ReadOnlyPropertyCheck;
use BambooHR\Guardrail\Checks\ReturnCheck;
use BambooHR\Guardrail\Checks\DependenciesOnVendorCheck;
use BambooHR\Guardrail\Checks\ServiceMethodDocumentationCheck;
use BambooHR\Guardrail\Checks\SplatCheck;
use BambooHR\Guardrail\Checks\StaticCallCheck;
use BambooHR\Guardrail\Checks\StaticPropertyFetchCheck;
use BambooHR\Guardrail\Checks\SwitchCheck;
use BambooHR\Guardrail\Checks\ThrowsCheck;
use BambooHR\Guardrail\Checks\UndefinedVariableCheck;
use BambooHR\Guardrail\Checks\UnreachableCodeCheck;
use BambooHR\Guardrail\Checks\UnsafeSuperGlobalCheck;
use BambooHR\Guardrail\Checks\UnusedPrivateMemberVariableCheck;
use BambooHR\Guardrail\Checks\UseStatementCaseCheck;
use BambooHR\Guardrail\Checks\OpenApiAttributeDocumentationCheck;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators as Ev;
use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class StaticAnalyzer
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class StaticAnalyzer extends NodeVisitorAbstract
{
	/** @var  SymbolTable */
	private $index;

	/** @var string */
	private $file;

	/** @var \BambooHR\Guardrail\Checks\BaseCheck[] */
	private $checks = [];

	private ScopeStack $scopeStack;

	/** @var array */
	private $timings = [];

	/** @var int[] */
	private $counts = [];

	/**
	 * @return array
	 */
	function getTimingsAndCounts() {
		return [$this->timings, $this->counts];
	}

	const EVALUATORS = [
		Ev\Catch_::class,
		Ev\Declare_::class,
		Ev\Expression::class,
		Ev\ExpressionStatement::class,
		Ev\ForEach_::class,
		Ev\FunctionLike::class,
		Ev\Global_::class,
		Ev\If_::class,
		Ev\Return_::class,
		Ev\StaticVar_::class
	];


	/**
	 * StaticAnalyzer constructor.
	 *
	 * @param SymbolTable           $index        The index
	 * @param OutputInterface       $output       Instance if OutputInterface
	 * @param MetricOutputInterface $metricOutput Instance of MetricOutputInterface
	 * @param Config                $config       The config
	 */
	function __construct(SymbolTable $index, OutputInterface $output, MetricOutputInterface $metricOutput, Config $config) {
		$this->index = $index;
		$this->scopeStack = new ScopeStack($output, $metricOutput, $config);
		$this->scopeStack->pushScope(new Scope(true, true, false));

		/** @var \BambooHR\Guardrail\Checks\BaseCheck[] $checkers */
		$checkers = [
			new DocBlockTypesCheck($this->index, $output),
			new EnumCheck($this->index, $output),
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
			new Psr4Check($this->index, $output, $config->getPsrRoots()),
			new CyclomaticComplexityCheck($this->index, $output, $metricOutput),
			new ConditionalAssignmentCheck($this->index, $output),
			new ClassMethodStringCheck($this->index, $output),
			new UnusedPrivateMemberVariableCheck($this->index, $output),
			new SplatCheck($this->index, $output),
			new PropertyStoreCheck($this->index, $output),
			new ImagickCheck($this->index, $output),
			new UnsafeSuperGlobalCheck($this->index, $output),
			new UseStatementCaseCheck($this->index, $output),
			new ReadOnlyPropertyCheck($this->index, $output),
			new ClassConstCheck($this->index, $output),
			new ThrowsCheck($this->index, $output),
			new CountableEmptinessCheck($this->index, $output),
			new GlobalFunctionCheck($this->index, $output),
			new DependenciesOnVendorCheck($this->index, $output, $metricOutput),
			new OpenApiAttributeDocumentationCheck($this->index, $output, $metricOutput),
			new ServiceMethodDocumentationCheck($this->index, $output, $metricOutput),
			//new ClassStoredAsVariableCheck($this->index, $output)
		];

		$checkers = array_merge($checkers, $config->getPlugins($this->index, $output));

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

	public function getEvaluator(Node $node): ?Ev\EvaluatorInterface {
		static $instances = null;
		if (!$instances) {
			$instances = [];
			foreach (self::EVALUATORS as $className) {
				$instances[] = new $className;
			}
		}

		foreach ($instances as $instance) {
			$type = $instance->getInstanceType();
			if (is_array($type)) {

				foreach ($type as $type2) {
					if ($node instanceof $type2) {
						return $instance;
					}
				}
			} else {
				if ($node instanceof $type) {
					return $instance;
				}
			}
		}
		if ($node instanceof Node\Expr) {
			throw new \InvalidArgumentException("No handler for node type " . get_class($node));
		}
		return null;
	}

	/**
	 * setFile
	 *
	 * @param string $name   The name
	 * @param Config $config
	 *
	 * @return void
	 */
	public function setFile($name, Config $config) {
		$this->file = $name;
		$this->scopeStack = new ScopeStack(
			$this->scopeStack->getOutput(), $this->scopeStack->getMetricOutput(), $config
		);
		$this->scopeStack->pushScope(new Scope(true, true, false));
		$this->scopeStack->setCurrentFile($name);
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of the node
	 *
	 * @return null
	 */
	public function enterNode(Node $node) {
		if ($node instanceof Trait_) {
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if ($node instanceof Node\Stmt\ClassLike) {
			$this->scopeStack->pushClass($node);
		}

		if (Config::shouldUseDocBlockForInlineVars() && !($node instanceof FunctionLike)) {
			$vars = $node->getAttribute("namespacedInlineVar");
			if (is_array($vars)) {
				foreach ($vars as $varName => $varType) {
					if (!empty($varType)) {
						$this->scopeStack->getCurrentScope()->setVarType($varName, $varType, $node->getLine());
					}
				}
			}
		}

		if ($node instanceof FunctionLike) {
			$this->updateFunctionEmit($node, $this->scopeStack, "push");
		}
		$evaluator = $this->getEvaluator($node);
		if ($evaluator instanceof Ev\OnEnterEvaluatorInterface) {
			$evaluator->onEnter($node, $this->index, $this->scopeStack);
		}
		$this->scopeStack->pushParentNode($node);
		return null;
	}

	/**
	 * leaveNode
	 *
	 * @param Node $node Instance of node
	 * @guardrail-ignore Standard.VariableFunctionCall
	 * @return void
	 */
	public function leaveNode(Node $node) {
		if ($node instanceof Trait_) {
			return;
		}

		$this->scopeStack->popParentNode();

		$evaluator = $this->getEvaluator($node);
		if ($evaluator instanceof Ev\OnExitEvaluatorInterface) {
			$evaluator->onExit($node, $this->index, $this->scopeStack);
		}

		$this->runChecks($node);

		if ($node instanceof Node\Stmt\ClassLike) {
			$this->scopeStack->popClass();
		}

		if ($node instanceof FunctionLike) {
			$this->updateFunctionEmit($node, $this->scopeStack, "pop");
		}

	}

	/**
	 * updateFunctionEmit
	 *
	 * @param Node\FunctionLike $func       Instance of FunctionLike
	 * @param ScopeStack        $scopeStack Instance of ScopeStack
	 * @param string            $pushOrPop  Push | Pop
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

	/**
	 * @param Node $node
	 * @return void
	 */
	private function runChecks(Node $node): void {
		$inside = $this->scopeStack->getCurrentClass();

		// By the time the checks run, we've evaluated all the child expressions and tagged them with types.
		$class = get_class($node);
		if (isset($this->checks[$class])) {
			$last = microtime(true);
			foreach ($this->checks[$class] as $check) {
				$start = $last;
				$check->run($this->file, $node, $inside, $this->scopeStack);
				$last = microtime(true);
				$name = get_class($check);
				$this->timings[$name] = ($this->timings[$name] ?? 0) + ($last - $start);
				$this->counts[$name] = ($this->counts[$name] ?? 0) + 1;
			}
		}
	}
}
