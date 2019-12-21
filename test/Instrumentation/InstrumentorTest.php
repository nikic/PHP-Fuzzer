<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Instrumentation\Instrumentor;
use PHPUnit\Framework\TestCase;

class InstrumentorTest extends TestCase {
    public function testInstrumentation() {
        $input = <<<'CODE'
<?php
function test() {
    $x;
    if ($x && $y) {
        $x;
    }
    while ($x || $y) {
        $x;
        do {
            yield $x;
        } while ($x);
    }
    for ($x; $x; $x) {
        foreach ($x as $x) {
            $x;
        }
    }
}
interface Foo {
    public function bar();
}
CODE;

        $expected = <<<'CODE'
<?php

function test()
{
    $___key = \InstrumentationContext::$prevBlock << 28 | 13;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 13;
    $x;
    if ($x && \InstrumentationContext::traceBlock(1, $y)) {
        $___key = \InstrumentationContext::$prevBlock << 28 | 2;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 2;
        $x;
    }
    $___key = \InstrumentationContext::$prevBlock << 28 | 3;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 3;
    while ($x || \InstrumentationContext::traceBlock(4, $y)) {
        $___key = \InstrumentationContext::$prevBlock << 28 | 8;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 8;
        $x;
        do {
            $___key = \InstrumentationContext::$prevBlock << 28 | 6;
            \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
            \InstrumentationContext::$prevBlock = 6;
            \InstrumentationContext::traceBlock(5, (yield $x));
        } while ($x);
        $___key = \InstrumentationContext::$prevBlock << 28 | 7;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 7;
    }
    for ($x; $x; $x) {
        $___key = \InstrumentationContext::$prevBlock << 28 | 11;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 11;
        foreach ($x as $x) {
            $___key = \InstrumentationContext::$prevBlock << 28 | 9;
            \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
            \InstrumentationContext::$prevBlock = 9;
            $x;
        }
        $___key = \InstrumentationContext::$prevBlock << 28 | 10;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 10;
    }
    $___key = \InstrumentationContext::$prevBlock << 28 | 12;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 12;
}
interface Foo
{
    public function bar();
}
CODE;

        $instrumentor = new Instrumentor('InstrumentationContext');
        $output = $instrumentor->instrument($input);
        $this->assertSame($expected, $output);
    }
}