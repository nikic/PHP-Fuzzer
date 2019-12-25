<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Instrumentation\FileInfo;
use PhpFuzzer\Instrumentation\Instrumentor;
use PHPUnit\Framework\TestCase;

class InstrumentorTest extends TestCase {
    public function testInstrumentation() {
        $input = <<<'CODE'
<?php
function test() {
    $x;
    if ($x && $y) {
        yield $x;
    }
    if ($x);
    while ($x || $y) {
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
    try { $x; }
    catch (E $y) {}
    finally { $a; }
}
interface Foo {
    public function bar();
}
CODE;

        $expected = <<<'CODE'
<?php
function test() {
    { $___key = (\InstrumentationContext::$prevBlock << 28) | 18; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 18; $x; }
    if ($x && \InstrumentationContext::traceBlock(1, $y)) {
        { $___key = (\InstrumentationContext::$prevBlock << 28) | 3; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 3; \InstrumentationContext::traceBlock(2, yield $x); }
    } $___key = (\InstrumentationContext::$prevBlock << 28) | 4; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 4;
    if ($x){ $___key = (\InstrumentationContext::$prevBlock << 28) | 5; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 5; } $___key = (\InstrumentationContext::$prevBlock << 28) | 6; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 6;
    while ($x || \InstrumentationContext::traceBlock(7, $y)) {
        { $___key = (\InstrumentationContext::$prevBlock << 28) | 10; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 10; $x; }
        $___key = (\InstrumentationContext::$prevBlock << 28) | 8; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 8; do {
            $x;
        } while ($x); $___key = (\InstrumentationContext::$prevBlock << 28) | 9; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 9;
    }
    for ($x; $x; $x) {
        { $___key = (\InstrumentationContext::$prevBlock << 28) | 13; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 13; foreach ($x as $x) {
            { $___key = (\InstrumentationContext::$prevBlock << 28) | 11; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 11; $x; }
        } $___key = (\InstrumentationContext::$prevBlock << 28) | 12; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 12; }
    } $___key = (\InstrumentationContext::$prevBlock << 28) | 14; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 14;
    try { $x; }
    catch (E $y) { $___key = (\InstrumentationContext::$prevBlock << 28) | 15; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 15; }
    finally { { $___key = (\InstrumentationContext::$prevBlock << 28) | 16; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 16; $a; } } $___key = (\InstrumentationContext::$prevBlock << 28) | 17; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 17;
}
interface Foo {
    public function bar();
}
CODE;

        $instrumentor = new Instrumentor('InstrumentationContext');
        $fileInfo = new FileInfo();
        $output = $instrumentor->instrument($input, $fileInfo);
        $this->assertSame($expected, $output);
        $this->assertNotEmpty($fileInfo->blockIndexToPos);

        // The number of lines should be preserved.
        $inputNewlines = substr_count($input, "\n");
        $outputNewlines = substr_count($output, "\n");
        $this->assertSame($inputNewlines, $outputNewlines);
    }
}