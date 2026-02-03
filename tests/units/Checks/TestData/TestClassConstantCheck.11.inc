<?php

interface ParentInterface3 {
	const PARENT_CONST3 = 'parent3';
}

interface ChildInterface3 extends ParentInterface3 {
	// Interface with parent - using parent:: in default method (PHP 8.0+)
	public function getParentConst() {
		return parent::PARENT_CONST3;
	}
}
