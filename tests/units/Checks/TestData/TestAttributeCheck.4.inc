<?php

#[Attribute(Attribute::TARGET_METHOD)]
class MethodOnlyAttribute {
}

#[MethodOnlyAttribute]
class TestClassForWrongTarget {
}
