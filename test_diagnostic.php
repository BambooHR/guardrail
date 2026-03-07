<?php

namespace Test;

class DiagnosticTest {
    public function testMethod() {
        // This should trigger a null method call error
        $maybeNull = null;
        $maybeNull->someMethod();
        
        // This should trigger an unused variable error
        $unusedVar = "hello";
        
        // This should trigger a type mismatch error
        $stringVar = "test";
        $stringVar = 123; // Assigning int to string
        
        return true;
    }
}
