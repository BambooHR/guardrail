<?php

//Nullable
function A(int $int): int | null {
    return null;
}

A(0);

//Non-Nullable
function B(int $int): int | float {
    return null;
}

B(0);