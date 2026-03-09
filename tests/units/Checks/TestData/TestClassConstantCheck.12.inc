<?php

// Test parent:: used in an enum (should error - enums can't use parent::)
enum MyEnum {
	case CASE_A;
	case CASE_B;
	
	public function test(): string {
		// This should trigger the else branch (line 102-104) since enum is neither Class_ nor Interface_
		return parent::SOME_CONST;
	}
}
