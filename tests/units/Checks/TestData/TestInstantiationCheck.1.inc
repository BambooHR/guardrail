<?php


// Unsafe
$time = new DateTime();

// Unsafe
$time2 = new DateTime("now");

// Unsafe
$timeImmutable = new DateTimeImmutable();

// Unsafe
$timeImmutable2 = new DateTimeImmutable($time);


// Safe
$time = new DateTime("now", new DateTimeZone("America/Denver"));

