<?php

namespace PhpFuzzer;

final class FuzzingContext {
    public static $prevBlock = -1;
    public static $edges = [];

    public static function reset() {
        self::$prevBlock = -1;
        self::$edges = [];
    }
}