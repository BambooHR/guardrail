<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Attributes;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use BambooHR\Guardrail\Util;
use PhpParser\Node\UnionType;

/**
 * Class MethodCall
 *
 * @package BambooHR\Guardrail\Checks
 */
class MethodCall extends CallCheck {

	/**
	 * MethodCall constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->inferenceEngine = new TypeInferrer($symbolTable);
		$this->callableCheck = new CallableCheck($symbolTable, $doc);
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\MethodCall::class];
	}



	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return mixed
	 */
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		static $checkable = 0, $uncheckable = 0;
		if ($node instanceof Expr\MethodCall) {
			if ($inside instanceof Trait_) {
				// Traits should be converted into methods in the class, so that we can check them in context.
				return;
			}
			if ($node->name instanceof Expr) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME, "Variable function name detected");
				return;
			}
			$methodName = strval($node->name);

			$className = "";
			$var = $node->var;
			if ($var instanceof Variable) {
				if ($var->name == "this" && !$inside) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't use \$this outside of a class");
					return;
				}
			}
			if ($scope) {
				list($className, $attributes) = $this->inferenceEngine->inferType($inside, $node->var, $scope);
			}
			if ($className  && $className != Scope::MIXED_TYPE && $attributes & Attributes::NULL_POSSIBLE) {
				$variable = ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) ? ' $' . $node->var->name : '';
				$this->emitError($fileName, $node, ErrorConstants::TYPE_NULL_DEREFERENCE, "Dereferencing potentially null object" . $variable);
			}
			if ($className != "" && $className[0] != "!") {
				if (!$this->symbolTable->isDefinedClass($className)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Unknown class $className in method call to $methodName()");
					return;
				}
				$method = Util::findAbstractedSignature($className, $methodName, $this->symbolTable);
				if ($method) {
					$this->checkMethod($fileName, $node, $className, $methodName, $scope, $method, $inside);
				} else {
					// If there is a magic __call method, then we can't know if it will handle these calls.
					if (
						!Util::findAbstractedMethod($className, "__call", $this->symbolTable) &&
						!$this->symbolTable->isParentClassOrInterface("iteratoriterator", $className) &&
						!$this->precededByMethodExistsCheck($node, $scope)
					) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Call to unknown method of $className::$methodName");
					}
				}
				$checkable ++;
			} else {
				$uncheckable++;
				//echo "Uncheckable method call $fileName ".$node->getLine()." ".$node->name." $checkable:$uncheckable\n";
			}
		}
	}

	/**
	 * checkMethod
	 *
	 * @param string          $fileName   The name of the file
	 * @param Node            $node       The node
	 * @param string          $className  The inside method
	 * @param string          $methodName The name of the method being checked
	 * @param Scope           $scope      Instance of Scope
	 * @param MethodInterface $method     Instance of MethodInterface
	 * @param ClassLike       $inside     What context we're executing inside (if any)
	 *
	 * @return void
	 */
	protected function checkMethod($fileName, $node, $className, $methodName, Scope $scope, MethodInterface $method, ClassLike $inside=null) {
		if ($method->isStatic()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, "Call to static method of $className::" . $method->getName() . " non-statically");
		}

		if ($method->getAccessLevel() == "private" && (!$inside || !isset($inside->namespacedName) || strcasecmp($className, $inside->namespacedName) != 0)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt call private method " . $methodName);
		} else if ($method->getAccessLevel() == "protected" && (!$inside || !isset($inside->namespacedName) || !$this->symbolTable->isParentClassOrInterface($className, $inside->namespacedName))) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to call protected method " . $methodName);
		}

		$params = $method->getParameters();
		$minimumArgs = $method->getMinimumRequiredParameters();
		if (count($node->args) < $minimumArgs) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT, "Function call parameter count mismatch to method " . $method->getName() . " (passed " . count($node->args) . " requires $minimumArgs)");
		}
		if (count($node->args) > count($params) && !$method->isVariadic()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT_EXCESS, "Too many parameters to non-variadic method " . $method->getName() . " (passed " . count($node->args) . " only takes " . count($params) . ")");
		}
		if ($method->isDeprecated()) {
			$errorType = $method->isInternal() ? ErrorConstants::TYPE_DEPRECATED_INTERNAL : ErrorConstants::TYPE_DEPRECATED_USER;
			$this->emitError($fileName, $node, $errorType, "Call to deprecated function " . $method->getName());
		}

		$name = $className . "->" . $methodName;
		$this->checkParams($fileName, $node, $name, $scope, $inside, $node->args, $params);
	}

	/**
	 * Is the method being called preceded by a logical check to see if the method_exists()?
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	private function precededByMethodExistsCheck(Node $node, Scope $scope = null): bool {
		if ($scope) {
			$stmts = $scope->getInsideFunction()->getStmts();
			foreach ($stmts as $stmt) {
				//If the node is within the statements found in the method we'll continue.
				if (
					$node->getLine() >= $stmt->getStartLine() &&
					$node->getLine() <= $stmt->getEndLine()
				) {
					//Iterate until we've past every expr node.
					$tmpStmt = clone $stmt;
					while ($this->checkNodeForSubNodeNames($tmpStmt, 'expr')) {
						$tmpStmt = $tmpStmt->expr;
					}
					$conditional = $tmpStmt;

					//Walk down the sequence to ensure we have a method_exists function call
					$sequence = ['cond', 'name', 'parts'];
					while (!empty($sequence) && $this->checkNodeForSubNodeNames($tmpStmt, $sequence[0])) {
						$tmpStmt = $tmpStmt->{$sequence[0]};
						array_shift($sequence);
					}

					return
						empty($sequence) &&                                      //Make sure the sequence is empty because it means we've found a function/method call
						in_array('method_exists', $tmpStmt) &&            //Ensure that the function is method_exists()
						!empty($conditional->cond->args[1]->value->value) &&     //Make sure the conditional has a value with arguments to the above function method_exists(). The 1st index will contain the method name being checked -- method_exists($object, 'method')
						!empty($node->name->name) &&                             //Make sure the node has a name (i.e. the method being called on the object)
						$conditional->cond->args[1]->value->value === $node->name->name //Make sure the method_exists() check matches the method being called on the node
					;
				}
			}
		}

		return false;
	}

	/**
	 * Check the given node for a specific sub node name
	 *
	 * @param Node   $node
	 * @param string $subNodeName
	 *
	 * @return bool
	 */
	private function checkNodeForSubNodeNames(Node $node, string $subNodeName): bool {
		return in_array($subNodeName, $node->getSubNodeNames());
	}
}
