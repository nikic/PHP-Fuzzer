<?php

namespace PhpFuzzer;

final class FuzzingContext {
    public static $prevBlock = -1;
    public static $edges = [];

    public static function reset() {
        self::$prevBlock = -1;
        self::$edges = [];
    }

    public static function traceBlock($blockIndex, $returnValue) {
        $key = self::$prevBlock << 32 | $blockIndex;
        self::$edges[$key] = (self::$edges[$key] ?? 0) + 1;
        return $returnValue;
    }
}