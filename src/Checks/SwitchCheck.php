<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
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
	public function getCheckNodeTypes(): array {
		return [ Switch_::class ];
	}

	/**
	 * endWithBreak
	 *
	 * @param array $stmts The statements
	 *
	 * @return bool
	 */
	static protected function endWithBreak(array $stmts) {
		$lastStatement = Util::getLastStatement($stmts);
		return
			$lastStatement == null ||
			$lastStatement instanceof \PhpParser\Node\Stmt\Break_ ||
			$lastStatement instanceof \PhpParser\Node\Stmt\Return_ ||
				(
					$lastStatement instanceof \PhpParser\Node\Stmt\Expression &&
					$lastStatement->expr instanceof Node\Expr\Exit_
				) || (
				(
					$lastStatement instanceof \PhpParser\Node\Stmt\Switch_ ||
					$lastStatement instanceof \PhpParser\Node\Stmt\If_
				) &&
				Util::allBranchesExit([$lastStatement])
			);
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node Instance of the Node
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run(string $fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node instanceof Switch_) {
			if (!Util::allBranchesExit([$node]) && is_array($node->cases)) {
				$nextError = null;
				/* Note: this algorithm (intentionally) doesn't output an error in the
				   final case clause.  A missing break there has no effect.
				*/
				foreach ($node->cases as $index => $case) {
					if ($nextError) {
						$nextError = $this->processCases($fileName, $case, $nextError);
					}
					if (!self::endWithBreak($case->stmts) && !Util::allBranchesExit($case->stmts)) {
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
