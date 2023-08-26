<?php

namespace PhpFuzzer;

final class FuzzingContext {
    /** @var int */
    public static $prevBlock = 0;
    /** @var array<int, int> */
    public static $edges = [];

    public static function reset(): void {
        self::$prevBlock = 0;
        self::$edges = [];
    }

    /**
     * @template T
     * @param int $blockIndex
     * @param T $returnValue
     * @return T
     */
    public static function traceBlock($blockIndex, $returnValue) {
        $key = self::$prevBlock << 28 | $blockIndex;
        self::$edges[$key] = (self::$edges[$key] ?? 0) + 1;
        return $returnValue;
    }
}
