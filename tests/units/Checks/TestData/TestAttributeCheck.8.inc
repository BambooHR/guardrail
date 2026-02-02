<?php
declare(strict_types=1);

#[Attribute]
class StringArgumentAttribute {
    public function __construct(string $name) {
    }
}

#[StringArgumentAttribute([])]
class TestClassForWrongArgType {
}
