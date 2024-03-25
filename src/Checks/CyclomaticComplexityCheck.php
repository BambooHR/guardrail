<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class CyclomaticComplexityCheck extends BaseCheck {

	function __construct(SymbolTable $symbolTable, OutputInterface $doc, private MetricOutputInterface $metricOutput) {
		parent::__construct($symbolTable, $doc);
	}

	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Node\Stmt\ClassMethod::class, Node\Stmt\Function_::class];
	}

	/**
	 * @param Node $node Any type of ATS node
	 * @return bool
	 */
	private static function isExpressionBranch(Node $node) {
		return
			$node instanceof Node\Expr\Ternary ||
			$node instanceof Node\Expr\BinaryOp\LogicalOr ||
			$node instanceof Node\Expr\BinaryOp\LogicalAnd ||
			$node instanceof Node\Expr\BinaryOp\LogicalXor ||
			$node instanceof Node\Expr\BinaryOp\BooleanAnd ||
			$node instanceof Node\Expr\BinaryOp\BooleanOr;
	}

	/**
	 * @param Node $node Any type of AST node
	 * @return bool
	 */
	private static function isStatementBranch(Node $node) {
		return
			$node instanceof Node\Stmt\Case_ ||
			$node instanceof Node\Stmt\Catch_ ||
			$node instanceof Node\Stmt\ElseIf_ ||
			$node instanceof Node\Stmt\For_ ||
			$node instanceof Node\Stmt\Foreach_ ||
			$node instanceof Node\Stmt\While_;
	}

	/**
	 * @param string $fileName   The file being scanned
	 * @param string $name       The name of the node being scanned
	 * @param Node   $node       Node node being scanned
	 * @param array  $statements An array of statements representing the function body.
	 * @return void
	 */
	function checkStatements($fileName, ?Node\Stmt\ClassLike $inside, Node $node, array $statements) {

		[$name, $type] = $this->getNodeNameAndType($node, $inside);
		$complexity = $this->calculateComplexity($statements);

		$this->metricOutput->emitMetric(
			new Metric(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_METRICS_COMPLEXITY,
				["complexity"=>$complexity,"name"=>$name]
			)
		);

		$minLine = $node->getStartLine();
		$maxLine = $node->getEndLine();

		$this->metricOutput->emitMetric(
			new Metric(
				$fileName,
				$node->getLine(),
				ErrorConstants::TYPE_METRICS_LINES_OF_CODE,
				["lines" => $maxLine - $minLine + 1, "type" => $type, "name"=>$name]
			)
		);
	}


	/**
	 * @param string                   $fileName The file being scanned
	 * @param Node                     $node     The node being scanned
	 * @param Node\Stmt\ClassLike|null $inside   The class we're inside
	 * @param Scope|null               $scope    Any other relevant scope
	 * @return void
	 */
	function run($fileName, Node $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		if ($node->stmts) {
			if ($node instanceof Node\Stmt\ClassMethod) {
				$this->checkStatements($fileName, $inside, $node, $node->stmts);
			} else if ($node instanceof Node\Stmt\Function_) {
				$this->checkStatements($fileName, $inside, $node, $node->stmts);
			}
		}
	}

	/**
	 * @param array $statements
	 * @return array
	 */
	public function calculateComplexity(array $statements): int {
		$complexity = 1;
		ForEachNode::run($statements, function (Node $node) use (&$complexity) {
			if (self::isStatementBranch($node) || self::isExpressionBranch($node)) {
				++$complexity;
			}
		});
		return $complexity;
	}

	/**
	 * @param Node $node
	 * @param Node\Stmt\ClassLike|null $inside
	 * @return array
	 */
	public function getNodeNameAndType(Node $node, ?Node\Stmt\ClassLike $inside): array {
		if ($node instanceof Node\Stmt\ClassMethod) {
			$type = "method";
			$className = isset($inside) && isset($inside->name) ? strval($inside->name) : "(anonymous)";
			$name = $className . ($node->isStatic() ? "->" : "::") . $node->name;
		} else {
			$type = "function";
			/** @var Node\Stmt\Function_ $node */
			$name = strval($node->name);
		}
		return array($name, $type);
	}
}