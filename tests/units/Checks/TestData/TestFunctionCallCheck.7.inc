<?php

// Unsafe
$today = date("Y-m-d");

// Unsafe
$todayDateTime = date_create("now");

$todayDateTimeImmutable = date_create_immutable("now");

// Safe
$gmToday = gmdate("Y-m-d");