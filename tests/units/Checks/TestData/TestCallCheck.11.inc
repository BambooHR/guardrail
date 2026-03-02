<?php

/**
 * @template T
 */
class Container {
    /**
     * @param T $value
     * @return void
     */
    public function setValue($value): void {}
}

$container = new Container();
$container->setValue("hello");
