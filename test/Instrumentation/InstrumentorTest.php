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
    ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 9];
    \InstrumentationContext::$prevBlock = 9;
    $x;
    if ($x) {
        ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 0];
        \InstrumentationContext::$prevBlock = 0;
        $x;
    }
    ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 1];
    \InstrumentationContext::$prevBlock = 1;
    while ($x) {
        ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 4];
        \InstrumentationContext::$prevBlock = 4;
        $x;
        do {
            ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 2];
            \InstrumentationContext::$prevBlock = 2;
            $x;
        } while ($x);
        ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 3];
        \InstrumentationContext::$prevBlock = 3;
    }
    for ($x; $x; $x) {
        ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 7];
        \InstrumentationContext::$prevBlock = 7;
        foreach ($x as $x) {
            ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 5];
            \InstrumentationContext::$prevBlock = 5;
            $x;
        }
        ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 6];
        \InstrumentationContext::$prevBlock = 6;
    }
    ++\InstrumentationContext::$edges[\InstrumentationContext::$prevBlock << 32 | 8];
    \InstrumentationContext::$prevBlock = 8;
}
CODE;

        $instrumentor = new Instrumentor();
        $output = $instrumentor->instrument($input);
        $this->assertSame($expected, $output);
    }
}