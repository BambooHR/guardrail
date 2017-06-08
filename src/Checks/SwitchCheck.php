<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use BambooHR\Guardrail\Scope;

/**
 * Class SwitchCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class SwitchCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ Switch_::class ];
	}

	/**
	 * getLastStatement
	 *
	 * @param array $stmts The statements
	 *
	 * @return mixed|null
	 */
	static protected function getLastStatement(array $stmts) {
		$lastStatement = null;
		foreach ($stmts as $stmt) {
			if (!$stmt instanceof Nop) {
				$lastStatement = $stmt;
			}
		}
		return $lastStatement;
	}

	/**
	 * endWithBreak
	 *
	 * @param array $stmts The statements from the node
	 *
	 * @return bool
	 */
	static protected function endWithBreak(array $stmts) {
		$lastStatement = self::getLastStatement($stmts);
		return
			$lastStatement == null ||
			$lastStatement instanceof Break_ ||
			$lastStatement instanceof Return_ ||
			$lastStatement instanceof Exit_ ||
			(
				$lastStatement instanceof FuncCall &&
				$lastStatement->name instanceof Name &&
				$lastStatement->name == "die"
			) || (
				(
					$lastStatement instanceof Switch_ ||
					$lastStatement instanceof If_
				) &&
				self::allBranchesExit([$lastStatement])
			);
	}

	/**
	 * allIfBranchesExit
	 *
	 * @param If_ $lastStatement Instance of If_
	 *
	 * @return bool
	 */
	static protected function allIfBranchesExit(If_ $lastStatement) {
		if (!$lastStatement->else && !$lastStatement->elseifs) {
			return false;
		}
		$trueCond = self::allBranchesExit($lastStatement->stmts);
		if (!$trueCond) {
			return false;
		}
		if ($lastStatement->else && !self::allBranchesExit($lastStatement->else->stmts)) {
			return false;
		}
		if ($lastStatement->elseifs) {
			foreach ($lastStatement->elseifs as $elseIf) {
				if (!self::allBranchesExit($elseIf->stmts)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * allSwitchCasesExit
	 *
	 * @param Switch_ $lastStatement Instance of Switch_
	 *
	 * @return bool
	 */
	static protected function allSwitchCasesExit(Switch_ $lastStatement) {
		$hasDefault = false;
		foreach ($lastStatement->cases as $case) {
			if (!$case->cond) {
				$hasDefault = true;
			}
			$stmts = $case->stmts;
			// Remove the trailing break (if found) and just look for a return the statement prior
			while ( ($last = end($stmts)) instanceof Break_ || $last instanceof Nop) {
				$stmts = array_slice($stmts, 0, -1);
			}
			if ($stmts && !self::allBranchesExit($stmts)) {
				return false;
			}
		}
		return $hasDefault;
	}

	/**
	 * allBranchesExit
	 *
	 * @param array $stmts List of statements
	 *
	 * @return bool
	 */
	static public function allBranchesExit(array $stmts) {
		$lastStatement = self::getLastStatement($stmts);
		if (!$lastStatement) {
			return false;
		} else if ($lastStatement instanceof Exit_ || $lastStatement instanceof Return_) {
			return true;
		} else if ($lastStatement instanceof If_) {
			return self::allIfBranchesExit($lastStatement);
		} else if ($lastStatement instanceof Switch_) {
			return self::allSwitchCasesExit($lastStatement);
		} else {
			return false;
		}
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
		if ($node instanceof Switch_) {
			if (!self::allBranchesExit([$node]) && is_array($node->cases)) {
				$nextError = null;
				/* Note: this algorithm (intentionally) doesn't output an error in the
				   final case clause.  A missing break there has no effect.
				*/
				foreach ($node->cases as $index => $case) {
					if ($nextError) {
						$nextError = $this->processCases($fileName, $case, $nextError);
					}
					if (false === self::endWithBreak($case->stmts) && false === self::allBranchesExit($case->stmts)) {
						$nextError = $case;
					}
				}
			}
		}
	}

	/**
	 * processCases
	 *
	 * @param string    $fileName  The file name
	 * @param string    $case      The case
	 * @param Case|null $nextError Optional instance of Case
	 *
	 * @return null
	 */
	private function processCases($fileName, $case, $nextError = null) {
		$comments = $case->getAttribute('comments');
		if (is_array($comments)) {
			/** @var \PhpParser\Comment\Doc $comment */
			foreach ($comments as $comment) {
				if (preg_match("/fall *through/i", $comment)) {
					$nextError = null;
				}
			}
		}
		if ($nextError) {
			$this->emitError($fileName, $nextError, ErrorConstants::TYPE_MISSING_BREAK, "Switch case does not end with break statement");
			$nextError = null;
		}

		return $nextError;
	}
}
