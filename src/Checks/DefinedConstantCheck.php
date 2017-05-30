<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class DefinedConstantCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class DefinedConstantCheck extends BaseCheck {

	/**
	 * @var array
	 */
	var $reflectedConstants = [];

	/**
	 * @var array
	 */
	static private $phpConstance = [
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

	/**
	 * DefinedConstantCheck constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $output      Instance of the OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $output) {
		parent::__construct($symbolTable, $output);

		foreach (get_loaded_extensions() as $extension) {
			try {
				$reflectedExtension = new \ReflectionExtension($extension);
				foreach ($reflectedExtension->getConstants() as $constant => $value) {
					$this->reflectedConstants[$constant] = true;
				}
			} catch (\ReflectionException $exception) {
			}
		}
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Node\Expr\ConstFetch::class];
	}

	/**
	 * isLanguageConst
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function isLanguageConst($name) {
		$consts = ["null","true","false"];
		foreach ($consts as $const) {
			if (strcasecmp($const, $name) == 0) {
				return true;
			}
		}
		return in_array($name, static::$phpConstance);
	}

	/**
	 * isMagicConstant
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function isMagicConstant($name) {
		return in_array($name, ['__LINE__','__FILE__','__DIR__','__FUNCTION__', '__CLASS__','__TRAIT__','__METHOD__','__NAMESPACE__']);
	}

	/**
	 * isExtensionConstant
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function isExtensionConstant($name) {
		return isset( $this->reflectedConstants[strval($name)] );
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		$name = $node->name;
		if (!$this->symbolTable->isDefined($name) && !$this->isLanguageConst($name) && !$this->isMagicConstant($name) && !$this->isExtensionConstant($name)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT, "That's not a thing.  Can't find define named \"$name\"");
			return;
		}
	}
}