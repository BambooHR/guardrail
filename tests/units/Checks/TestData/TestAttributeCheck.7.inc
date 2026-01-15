<?php

#[Attribute]
class OneArgumentAttribute {
    public function __construct(string $name) {
    }
}

#[OneArgumentAttribute]
class TestClassForWrongNumberOfArgs {
}
