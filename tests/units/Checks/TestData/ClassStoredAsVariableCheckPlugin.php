<?php

use BambooHR\Guardrail\Checks\ClassStoredAsVariableCheck;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Output\OutputInterface;

return function (SymbolTable $index, OutputInterface $output) {
	return new ClassStoredAsVariableCheck($index, $output);
};
