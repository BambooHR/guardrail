<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2024, BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\Stmt\Function_;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class GlobalFunctionCheck
 *
 * Checks that functions are not defined at the global level.
 * Functions should be placed in namespaces, classes, or conditional blocks.
 *
 * @package BambooHR\Guardrail\Checks
 */
class GlobalFunctionCheck extends BaseCheck {

    /**
     * GlobalFunctionCheck constructor.
     *
     * @param SymbolTable     $symbolTable Instance of the SymbolTable
     * @param OutputInterface $output      Instance of the OutputInterface
     */
    public function __construct(SymbolTable $symbolTable, OutputInterface $output) {
        parent::__construct($symbolTable, $output);
    }

    /**
     * getCheckNodeTypes
     *
     * @return array
     */
    public function getCheckNodeTypes() {
        return [Function_::class];
    }

    /**
     * Run the check on a function node
     *
     * @param string $fileName The file being analyzed
     * @param Function_ $node The function node
     * @param ClassLike|null $inside The class containing the function (null if global)
     * @param Scope|null $scope The current scope
     * @return void
     */

    public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
        if (!$node instanceof Function_) {
            return;
        }

        // Skip functions inside classes (methods)
        if ($inside !== null) {
            return;
        }

        // Skip functions inside namespaces (not in global namespace)
        // Functions in namespaces have a namespace prefix in their name
        if (strpos($node->namespacedName->toString(), '\\') !== false) {
            return;
        }

        // Skip functions inside conditional blocks (like if/else)
        if ($scope && $this->isInsideConditionalDeclaration($node, $scope)) {
            return;
        }

        // If we get here, the function is in the global namespace and not inside a conditional block
        // This is not allowed
        $name = $node->namespacedName->toString();
        $this->emitError(
            $fileName,
            $node,
            ErrorConstants::TYPE_GLOBAL_FUNCTION,
            "Function $name() is defined at the global level. Functions should be placed in namespaces, classes, or conditional blocks"
        );
    }

    /**
     * Check if a function is declared inside a conditional block (like if/else)
     *
     * @param Function_ $node The function node
     * @param Scope $scope The current scope
     * @return bool True if inside a conditional declaration
     */
    private function isInsideConditionalDeclaration(Function_ $node, Scope $scope) {
        // Get parent nodes from the scope
        $parents = $scope->getParentNodes();

        foreach ($parents as $parent) {
            if ($parent instanceof Node\Stmt\If_ ||
                $parent instanceof Node\Stmt\ElseIf_ ||
                $parent instanceof Node\Stmt\Else_) {
                return true;
            }
        }
        return false;
    }
}
