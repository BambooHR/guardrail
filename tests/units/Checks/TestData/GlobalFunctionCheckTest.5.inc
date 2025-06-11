<?php

// This file contains a function inside an if statement with a more complex condition
// to test the isInsideConditionalDeclaration method specifically
if ($someVariable === true && !empty($anotherVariable)) {
    function test_complex_conditional_function() {
        return "Function inside a complex conditional block";
    }
}
