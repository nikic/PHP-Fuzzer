<?php declare(strict_types=1);

use PhpFuzzer\Instrumentation\Instrumentor;
use PHPUnit\Framework\TestCase;

class InstrumentorTest extends TestCase {
    public function testInstrumentation() {
        $input = <<<'CODE'
<?php
function test() {
    $x;
    if ($x) {
        $x;
    }
    while ($x) {
        $x;
        do {
            $x;
        } while ($x);
    }
    for ($x; $x; $x) {
        foreach ($x as $x) {
            $x;
        }
    }
}
CODE;

        $expected = <<<'CODE'
<?php

function test()
{
    $___key = \InstrumentationContext::$prevBlock << 32 | 9;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 9;
    $x;
    if ($x) {
        $___key = \InstrumentationContext::$prevBlock << 32 | 0;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 0;
        $x;
    }
    $___key = \InstrumentationContext::$prevBlock << 32 | 1;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 1;
    while ($x) {
        $___key = \InstrumentationContext::$prevBlock << 32 | 4;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 4;
        $x;
        do {
            $___key = \InstrumentationContext::$prevBlock << 32 | 2;
            \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
            \InstrumentationContext::$prevBlock = 2;
            $x;
        } while ($x);
        $___key = \InstrumentationContext::$prevBlock << 32 | 3;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 3;
    }
    for ($x; $x; $x) {
        $___key = \InstrumentationContext::$prevBlock << 32 | 7;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 7;
        foreach ($x as $x) {
            $___key = \InstrumentationContext::$prevBlock << 32 | 5;
            \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
            \InstrumentationContext::$prevBlock = 5;
            $x;
        }
        $___key = \InstrumentationContext::$prevBlock << 32 | 6;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 6;
    }
    $___key = \InstrumentationContext::$prevBlock << 32 | 8;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 8;
}
CODE;

        $instrumentor = new Instrumentor('InstrumentationContext');
        $output = $instrumentor->instrument($input);
        $this->assertSame($expected, $output);
    }
}