<?php

// This file contains a function inside a conditional block (should be allowed)
if (true) {
    function test_conditional_function() {
        return "Function inside a conditional block";
    }
}
