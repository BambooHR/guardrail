<?php

namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2024, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;

/**
 * Detect whether a function/method literally always exits via throw or exit.
 *
 * Does not resolve cross-function calls — only structural patterns inside the
 * function body (throw, exit, if/else, switch, try/catch, while(true), do/while).
 * Used by the JSON symbol table indexer to set an `always_throws` attribute on
 * each method/function before stmts are dropped from serialization, so that
 * later checks can answer "does this call always throw?" without re-parsing.
 */
class ThrowDetector {
	public static function functionAlwaysThrows(Node\FunctionLike $node): bool {
		$stmts = $node->getStmts();
		if (!$stmts) {
			return false;
		}
		return self::statementsAlwaysThrow($stmts);
	}

	private static function statementsAlwaysThrow(array $stmts): bool {
		$last = null;
		for ($i = count($stmts) - 1; $i >= 0; $i--) {
			if (!($stmts[$i] instanceof Node\Stmt\Nop)) {
				$last = $stmts[$i];
				break;
			}
		}
		if (!$last) {
			return false;
		}
		if ($last instanceof Node\Stmt\Throw_) {
			return true;
		}
		if ($last instanceof Node\Stmt\Expression && $last->expr instanceof Node\Expr\Exit_) {
			return true;
		}
		if ($last instanceof Node\Stmt\If_) {
			if (!$last->else) {
				return false;
			}
			if (!self::statementsAlwaysThrow($last->stmts)) {
				return false;
			}
			if (!self::statementsAlwaysThrow($last->else->stmts)) {
				return false;
			}
			foreach ($last->elseifs as $elseIf) {
				if (!self::statementsAlwaysThrow($elseIf->stmts)) {
					return false;
				}
			}
			return true;
		}
		if ($last instanceof Node\Stmt\Switch_) {
			$hasDefault = false;
			foreach ($last->cases as $case) {
				if ($case->cond === null) {
					$hasDefault = true;
				}
				$caseStmts = $case->stmts;
				while (($tail = end($caseStmts)) instanceof Node\Stmt\Break_ || $tail instanceof Node\Stmt\Nop) {
					$caseStmts = array_slice($caseStmts, 0, -1);
				}
				if ($caseStmts && !self::statementsAlwaysThrow($caseStmts)) {
					return false;
				}
			}
			return $hasDefault;
		}
		if ($last instanceof Node\Stmt\TryCatch) {
			if ($last->finally && self::statementsAlwaysThrow($last->finally->stmts)) {
				return true;
			}
			if (!self::statementsAlwaysThrow($last->stmts)) {
				return false;
			}
			foreach ($last->catches as $catch) {
				if (!self::statementsAlwaysThrow($catch->stmts)) {
					return false;
				}
			}
			return true;
		}
		if ($last instanceof Node\Stmt\While_) {
			if ($last->cond instanceof Node\Expr\ConstFetch && strtolower($last->cond->name->toString()) === 'true') {
				return self::statementsAlwaysThrow($last->stmts);
			}
			return false;
		}
		if ($last instanceof Node\Stmt\Do_) {
			return self::statementsAlwaysThrow($last->stmts);
		}
		return false;
	}
}
