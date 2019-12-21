<?php

namespace PhpFuzzer;

final class FuzzingContext {
    public static $prevBlock = 0;
    public static $edges = [];

    public static function reset() {
        self::$prevBlock = 0;
        self::$edges = [];
    }

    public static function traceBlock($blockIndex, $returnValue) {
        $key = self::$prevBlock << 28 | $blockIndex;
        self::$edges[$key] = (self::$edges[$key] ?? 0) + 1;
        return $returnValue;
    }
}