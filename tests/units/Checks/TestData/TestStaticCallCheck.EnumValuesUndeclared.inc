<?php

enum UndeclaredValuesEnum: string
{
    case Foo = 'foo';
}

UndeclaredValuesEnum::values();
