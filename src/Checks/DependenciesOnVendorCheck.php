<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;

class DependenciesOnVendorCheck extends BaseCheck {
	function __construct($index, $output, private MetricOutputInterface $metricOutput) {
		parent::__construct($index, $output);
	}

	function getCheckNodeTypes() {
		return [Node\Stmt\Class_::class];
	}

	function run($fileName, Node $node, ?Node\Stmt\ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Node\Stmt\Class_) {
			foreach ($this->getAllNames($node) as $referenceFileName) {
				if ($referenceFileName && preg_match("@[/\\\\]?vendor[/\\\\]([-a-z_0-9]+[/\\\\][-a-z_0-9]+)@", $referenceFileName, $matches)) {
					$className = $node->namespacedName ?? $node->name ?? "anonymous";
					$this->metricOutput->emitMetric(
						new Metric(
							$fileName,
							$node->getLine(),
							"Standard.Metrics.Vendors",
							["class" => strval($className), "references" => $matches[1], "full_path" => strval($referenceFileName)]
						)
					);
				}
			}
		}
	}

	function getNameFile(string $innerNode): string {
		$name = strtolower($innerNode);
		if (Util::isScalarType($name)) {
			return "";
		}

		if (str_contains($name, "\\")) {
			return $this->symbolTable->getClassFile($name) ??
				$this->symbolTable->getInterfaceFile($name) ??
				$this->symbolTable->getFunctionFile($name) ?? "";
		}

		return "";
	}

	/**
	 * @param Node\Stmt\Class_ $node
	 *
	 * @return array
	 */
	public function getAllNames(Node\Stmt\Class_ $node): array {
		$class = [];
		ForEachNode::run([$node], function ($innerNode) use (&$class) {
			if ($innerNode instanceof Node\Name) {
				$file = $this->getNameFile($innerNode);
				if ($file !== "") {
					$class[] = $file;
				}
			}
		});

		return array_unique($class);
	}
}