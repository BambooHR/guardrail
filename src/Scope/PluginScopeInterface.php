<?php

namespace BambooHR\Guardrail\Scope;

use BambooHR\Guardrail\Scope as ScopeInterface;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

interface PluginScopeInterface
{
	public function isStrict(): bool;
	public function isStatic(): bool;
	public function isGlobal(): bool;
	public function getInsideFunction(): ?FunctionLike;
	public function setVarType($name, Name|Identifier|ComplexType|null $type, $line): void;
	public function setVarReference($name, ScopeVar $ref): void;
	public function setVarWritten($name, $line): void;
	public function setVarUsed($name): void;
	public function dump($typeChanged = false, $used = false, $modified = false): void;
	public function markAllVarsUsed(): void;
	public function getUnusedVars(): array;
	public function getUsedVars(): array;
	public function getTypeChangedVars(): array;
	function getVarType($name): Name|Identifier|ComplexType|null;
	function getVarExists(string $name): bool;
	function getVarObject($name): ?ScopeVar;
}
