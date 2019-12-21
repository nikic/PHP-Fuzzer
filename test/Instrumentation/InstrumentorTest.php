<?php declare(strict_types=1);

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
    $___key = \InstrumentationContext::$prevBlock << 32 | 12;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 12;
    $x;
    if ($x && \InstrumentationContext::traceBlock(0, $y)) {
        $___key = \InstrumentationContext::$prevBlock << 32 | 1;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 1;
        $x;
    }
    $___key = \InstrumentationContext::$prevBlock << 32 | 2;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 2;
    while ($x || \InstrumentationContext::traceBlock(3, $y)) {
        $___key = \InstrumentationContext::$prevBlock << 32 | 7;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 7;
        $x;
        do {
            $___key = \InstrumentationContext::$prevBlock << 32 | 5;
            \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
            \InstrumentationContext::$prevBlock = 5;
            \InstrumentationContext::traceBlock(4, (yield $x));
        } while ($x);
        $___key = \InstrumentationContext::$prevBlock << 32 | 6;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 6;
    }
    for ($x; $x; $x) {
        $___key = \InstrumentationContext::$prevBlock << 32 | 10;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 10;
        foreach ($x as $x) {
            $___key = \InstrumentationContext::$prevBlock << 32 | 8;
            \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
            \InstrumentationContext::$prevBlock = 8;
            $x;
        }
        $___key = \InstrumentationContext::$prevBlock << 32 | 9;
        \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
        \InstrumentationContext::$prevBlock = 9;
    }
    $___key = \InstrumentationContext::$prevBlock << 32 | 11;
    \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1;
    \InstrumentationContext::$prevBlock = 11;
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