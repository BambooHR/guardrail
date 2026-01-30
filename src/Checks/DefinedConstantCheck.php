<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionException;
use ReflectionExtension;

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
	static private $phpConstants = [
		"PHP_VERSION" => 1,
		"PHP_MAJOR_VERSION" => 1,
		"PHP_MINOR_VERSION" => 1,
		"PHP_RELEASE_VERSION" => 1,
		"PHP_VERSION_ID" => 1,
		"PHP_EXTRA_VERSION" => 1,
		"PHP_ZTS" => 1,
		"PHP_DEBUG" => 1,
		"PHP_MAXPATHLEN" => 1,
		"PHP_OS" => 1,
		"PHP_SAPI" => 1,
		"PHP_EOL" => 1,
		"PHP_INT_MAX" => 1,
		"PHP_INT_MIN" => 1,
		"PHP_INT_SIZE" => 1,
		"DEFAULT_INCLUDE_SIZE" => 1,
		"PEAR_INSTALL_DIR" => 1,
		"PEAR_EXTENSION_DIR" => 1,
		"PHP_EXTENSION_DIR" => 1,
		"PHP_PREFIX" => 1,
		"PHP_BINDIR" => 1,
		"PHP_BINARY" => 1,
		"PHP_MANDIR" => 1,
		"PHP_LIBDIR" => 1,
		"PHP_DATADIR" => 1,
		"PHP_SYSCONFDIR" => 1,
		"PHP_LOCALSTATEDIR" => 1,
		"PHP_CONFIG_FILE_PATH" => 1,
		"PHP_CONFIG_FILE_SCAN_DIR" => 1,
		"PHP_SHLIB_SUFFIX" => 1,
		"E_ERROR" => 1,
		"E_WARNING" => 1,
		"E_PARSE" => 1,
		"E_NOTICE" => 1,
		"E_CORE_ERROR" => 1,
		"E_CORE_WARNING" => 1,
		"E_COMPILE_ERROR" => 1,
		"E_COMPILE_WARNING" => 1,
		"E_USER_ERROR" => 1,
		"E_USER_WARNING" => 1,
		"E_USER_NOTICE" => 1,
		"E_DEPRECATED" => 1,
		"E_USER_DEPRECATED" => 1,
		"E_ALL" => 1,
		"E_STRICT" => 1,
		"__COMPILER_HALT_OFFSET__" => 1
	];

	static $magicConstants = [
		'__LINE__' => 1,
		'__FILE__' => 1,
		'__DIR__' => 1,
		'__FUNCTION__' => 1,
		'__CLASS__' => 1,
		'__TRAIT__' => 1,
		'__METHOD__' => 1,
		'__NAMESPACE__' => 1
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
				$reflectedExtension = new ReflectionExtension($extension);
				foreach ($reflectedExtension->getConstants() as $constant => $value) {
					$this->reflectedConstants[$constant] = true;
				}
			} catch (ReflectionException $exception) {
			}
		}
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ConstFetch::class];
	}

	/**
	 * isLanguageConst
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function isLanguageConst($name) {
		return
			strcasecmp($name, 'null') == 0 ||
			strcasecmp($name, 'true') == 0 ||
			strcasecmp($name, 'false') == 0 ||
			array_key_exists($name, self::$phpConstants);
	}

	/**
	 * isMagicConstant
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function isMagicConstant($name) {
		return array_key_exists($name, self::$magicConstants);
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
	 * @param string $namespacedName Namespaced name if possible
	 * @param string $name           Original name of the constant reference.
	 * @return bool
	 */
	protected function constantIsDefined($namespacedName, $name) {
		if ($namespacedName && $this->symbolTable->isDefined($namespacedName)) {
			return true;
		}
		if ($namespacedName != $name && $this->symbolTable->isDefined($name)) {
			return true ;
		}
		return false;
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
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof ConstFetch) {
			$namespacedName = $node->name->hasAttribute('namespacedName') ? $node->name->getAttribute('namespacedName')->toString() : "";
			$name = $node->name->toString();

			if (!$this->isLanguageConst($name) && !$this->isMagicConstant($name) && !$this->isExtensionConstant($name) && !$this->constantIsDefined($namespacedName, $name)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT, "That's not a thing.  Can't find define named \"$name\"");
				return;
			}
		}
	}
}