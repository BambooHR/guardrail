<?php

trait TestTrait {
    public function traitMethod(): int {
        return [];
    }
}

class TestClass {
    use TestTrait;
}
