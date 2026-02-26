<?php
try {
} catch (Exception $outer) {
    try {
        throw $outer;
    } catch (Exception $inner) {
    }
}