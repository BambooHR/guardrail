<?php

namespace BambooHR\Guardrail\Scope;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope as ScopeInterface;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

class ScopeStack implements ScopeInterface {
	private array $classes = [];
	private array $scopes = [];

	private array $parentNodes = [];

	private string $currentFile;

	function __construct(
		private OutputInterface $output,
		private MetricOutputInterface $metricOutput,
		private Config $config,
	) { }

	function getConfig(): Config {
		return $this->config;
	}

	function getOutput(): OutputInterface {
		return $this->output;
	}

	public function getMetricOutput(): MetricOutputInterface {
		return $this->metricOutput;
	}

	function pushClass(Node\Stmt\ClassLike $class):void {
		$this->classes[] = $class;
	}

	function popClass():Node\Stmt\ClassLike {
		return array_pop($this->classes);
	}

	function getParentNodes():array {
		return $this->parentNodes;
	}

	function getParent():Node {
		return $this->parentNodes[array_key_last($this->parentNodes)];
	}

	function pushParentNode(Node $node):void {
		$this->parentNodes[] = $node;
	}

	function popParentNode() {
		array_pop($this->parentNodes );
	}

	function pushScope(PluginScopeInterface $scope):void {
		$this->scopes[] = $scope;
	}

	function swapTopTwoScopes() {
		$count = count($this->scopes);
		$tmp = $this->scopes[$count - 2];
		$this->scopes[$count - 2] = $this->scopes[$count - 1];
		$this->scopes[$count - 1] = $tmp;
	}

	function popScope():PluginScopeInterface {
		return array_pop($this->scopes);
	}

	function getCurrentScope():Scope {
		return end($this->scopes);
	}

	function getCurrentClass():?Node\Stmt\ClassLike {
		if (count($this->classes) == 0) {
			return null;
		} else {
			return end($this->classes);
		}
	}

	public function isStatic(): bool {
		return $this->getCurrentScope()->isStatic();
		// TODO: Implement isStatic() method.
	}

	public function isStrict():bool {
		return $this->getCurrentScope()->isStrict();
	}
	public function isGlobal(): bool {
		return $this->getCurrentScope()->isGlobal();
	}

	public function getInsideFunction(): ?FunctionLike {
		return $this->getCurrentScope()->getInsideFunction();
	}

	public function setVarType($name, Name|Identifier|ComplexType|null $type, $line): void {
		$this->getCurrentScope()->setVarType($name, $type, $line);
	}

	public function setVarReference($name, ScopeVar $ref): void {
		$this->getCurrentScope()->setVarReference($name, $ref);
	}

	public function setVarWritten($name, $line): void {

		$this->getCurrentScope()->getInsideFunction()?->getAttribute('function-scope')?->setVarWritten($name, $line);
	}

	public function setVarUsed($name): void {
		$this->getCurrentScope()->getInsideFunction()?->getAttribute('function-scope')?->setVarUsed($name);
	}

	public function dump($typeChanged = false, $used = false, $modified = false): void {
		$this->getCurrentScope()->dump();
	}

	public function markAllVarsUsed(): void {
		$this->getCurrentScope()->markAllVarsUsed();
	}

	public function getUnusedVars(): array {
		return $this->getCurrentScope()->getInsideFunction()?->getAttribute('function-scope')?->getUnusedVars() ?? [];
	}

	public function getUsedVars():array {
		return $this->getCurrentScope()->getInsideFunction()?->getAttribute('function-scope')?->getUnusedVars() ?? [];
	}

	public function getTypeChangedVars(): array {
		return $this->getCurrentScope()->getTypeChangedVars();
	}

	function getVarType($name): Name|Identifier|ComplexType|null {
		return $this->getCurrentScope()->getVarType($name);
	}

	function getVarExists(string $name): bool {
		return $this->getInsideFunction()?->getAttribute('function-scope')?->getVarExists($name) ?? false;
	}

	function getVarObject($name): ?ScopeVar {
		return $this->getInsideFunction()?->getAttribute('function-scope')?->getVarObject($name);
	}

	function getScopeClone(): Scope {
		return $this->getCurrentScope()->getScopeClone();
	}

	public function merge(ScopeInterface $other): void {
		$this->getCurrentScope()->merge($other);
	}

	public function setCurrentFile($file): void {
		$this->currentFile = $file;
	}

	public function getCurrentFile(): string {
		return $this->currentFile;
	}
}