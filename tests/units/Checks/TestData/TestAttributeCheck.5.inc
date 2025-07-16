<?php

#[Attribute]
class NotRepeatableAttribute {
}

#[NotRepeatableAttribute]
#[NotRepeatableAttribute]
class TestClassForNotRepeatableAttribute {
}
