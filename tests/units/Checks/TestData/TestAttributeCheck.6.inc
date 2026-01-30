<?php

#[Attribute]
class PrivateConstructorAttribute {
    private function __construct() {
    }
}

#[PrivateConstructorAttribute]
class TestClassForAttributeWithPrivateConstructor {
}
