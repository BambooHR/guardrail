<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Name;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use N98\JUnitXml;

class DefinedConstantCheck extends BaseCheck {
	var $reflectedConstants = [];
	static private $PHP_CONSTANTS = [
		"PHP_VERSION",
		"PHP_MAJOR_VERSION",
		"PHP_MINOR_VERSION",
		"PHP_RELEASE_VERSION",
		"PHP_VERSION_ID",
		"PHP_EXTRA_VERSION",
		"PHP_ZTS",
		"PHP_DEBUG",
		"PHP_MAXPATHLEN",
		"PHP_OS",
		"PHP_SAPI",
		"PHP_EOL",
		"PHP_INT_MAX",
		"PHP_INT_MIN",
		"PHP_INT_SIZE",
		"DEFAULT_INCLUDE_SIZE",
		"PEAR_INSTALL_DIR",
		"PEAR_EXTENSION_DIR",
		"PHP_EXTENSION_DIR",
		"PHP_PREFIX",
		"PHP_BINDIR",
		"PHP_BINARY",
		"PHP_MANDIR",
		"PHP_LIBDIR",
		"PHP_DATADIR",
		"PHP_SYSCONFDIR",
		"PHP_LOCALSTATEDIR",
		"PHP_CONFIG_FILE_PATH",
		"PHP_CONFIG_FILE_SCAN_DIR",
		"PHP_SHLIB_SUFFIX",
		"E_ERROR",
		"E_WARNING",
		"E_PARSE",
		"E_NOTICE",
		"E_CORE_ERROR",
		"E_CORE_WARNING",
		"E_COMPILE_ERROR",
		"E_COMPILE_WARNING",
		"E_USER_ERROR",
		"E_USER_WARNING",
		"E_USER_NOTICE",
		"E_DEPRECATED",
		"E_USER_DEPRECATED",
		"E_ALL",
		"E_STRICT",
		"__COMPILER_HALT_OFFSET__"
	];

	function __construct(SymbolTable $symbolTable, \BambooHR\Guardrail\Output\OutputInterface $output) {
		parent::__construct($symbolTable, $output);

		foreach(get_loaded_extensions() as $extension) {
			try {
				$reflectedExtension = new \ReflectionExtension($extension);
				foreach($reflectedExtension->getConstants() as $constant=>$value) {
					$this->reflectedConstants[ $constant ] = true;
				}
			}
			catch(\ReflectionException $e) {
			}
		}
	}

	function getCheckNodeTypes() {
		return [Node\Expr\ConstFetch::class];
	}

	function isLanguageConst($name) {
		$consts = ["null","true","false"];
		foreach($consts as $const) {
			if(strcasecmp($const,$name)==0) return true;
		}
		return in_array($name,static::$PHP_CONSTANTS);
	}

	function isMagicConstant($name) {
		return in_array($name, ['__LINE__','__FILE__','__DIR__','__FUNCTION__', '__CLASS__','__TRAIT__','__METHOD__','__NAMESPACE__']);
	}

	function isExtensionConstant($name) {
		return isset( $this->reflectedConstants[strval($name)] );
	}

	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		$name = $node->name;
		// Note: defined() will check the global defines available in the current process, which could differ from the run-time environment the
		// code is executed under.  Unfortunately, there isn't a good way to enumerate extension constants.
		if (!$this->symbolTable->isDefined($name) && !$this->isLanguageConst($name) && !$this->isMagicConstant($name) && !$this->isExtensionConstant($name)) {
			$this->emitError($fileName, $node, self::TYPE_UNKNOWN_GLOBAL_CONSTANT, "That's not a thing.  Can't find define named \"$name\"");
			return;
		}
	}
}