<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Instrumentation\FileInfo;
use PhpFuzzer\Instrumentation\Instrumentor;
use PhpFuzzer\Instrumentation\MutableString;
use PhpParser\PhpVersion;
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
    fn($x) => $x;
    $x ?? $y;
    $x ??= $y;
    match ($x) {
        1, 2 => $y,
        default => $z,
    };
    switch ($x) {
        case 1:
        case 2:
            $x;
        default:
            $x;
    }
}
interface Foo {
    public function bar();
}
CODE;

        $expected = <<<'CODE'
<?php
function test() {
    { $___key = (\InstrumentationContext::$prevBlock << 28) | 28; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 28; $x; }
    if ($x && \InstrumentationContext::traceBlock(1, $y)) {
        { $___key = (\InstrumentationContext::$prevBlock << 28) | 3; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 3; \InstrumentationContext::traceBlock(2, yield $x); }
    } $___key = (\InstrumentationContext::$prevBlock << 28) | 4; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 4;
    if ($x){ $___key = (\InstrumentationContext::$prevBlock << 28) | 5; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 5; } $___key = (\InstrumentationContext::$prevBlock << 28) | 6; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 6;
    while ($x || \InstrumentationContext::traceBlock(7, $y)) {
        { $___key = (\InstrumentationContext::$prevBlock << 28) | 10; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 10; $x; }
        do {
            { $___key = (\InstrumentationContext::$prevBlock << 28) | 8; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 8; $x; }
        } while ($x); $___key = (\InstrumentationContext::$prevBlock << 28) | 9; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 9;
    } $___key = (\InstrumentationContext::$prevBlock << 28) | 11; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 11;
    for ($x; $x; $x) {
        { $___key = (\InstrumentationContext::$prevBlock << 28) | 14; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 14; foreach ($x as $x) {
            { $___key = (\InstrumentationContext::$prevBlock << 28) | 12; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 12; $x; }
        } $___key = (\InstrumentationContext::$prevBlock << 28) | 13; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 13; }
    } $___key = (\InstrumentationContext::$prevBlock << 28) | 15; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 15;
    try { $x; }
    catch (E $y) { $___key = (\InstrumentationContext::$prevBlock << 28) | 16; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 16; }
    finally { { $___key = (\InstrumentationContext::$prevBlock << 28) | 17; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 17; $a; } } $___key = (\InstrumentationContext::$prevBlock << 28) | 18; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 18;
    fn($x) => \InstrumentationContext::traceBlock(19, $x);
    $x ?? \InstrumentationContext::traceBlock(20, $y);
    $x ??= \InstrumentationContext::traceBlock(21, $y);
    match ($x) {
        1, 2 => \InstrumentationContext::traceBlock(22, $y),
        default => \InstrumentationContext::traceBlock(23, $z),
    };
    switch ($x) {
        case 1: $___key = (\InstrumentationContext::$prevBlock << 28) | 24; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 24;
        case 2:
            { $___key = (\InstrumentationContext::$prevBlock << 28) | 25; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 25; $x; }
        default:
            { $___key = (\InstrumentationContext::$prevBlock << 28) | 26; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 26; $x; }
    } $___key = (\InstrumentationContext::$prevBlock << 28) | 27; \InstrumentationContext::$edges[$___key] = (\InstrumentationContext::$edges[$___key] ?? 0) + 1; \InstrumentationContext::$prevBlock = 27;
}
interface Foo {
    public function bar();
}
CODE;

        $expectedCoverage = <<<'CODE'
<?php
!function test() {
    $x;
    !if ($x && !$y) {
        !yield $x;
    !}
    !if ($x)!;
    !while ($x || !$y) {
        $x;
        !do {
            $x;
        } while ($x)!;
    !}
    !for ($x; $x; $x) {
        !foreach ($x as $x) {
            $x;
        !}
    !}
    try { $x; }
    !catch (E $y) {}
    !finally { $a; !}
    fn($x) => !$x;
    $x ?? !$y;
    $x ??= !$y;
    match ($x) {
        1, 2 => !$y,
        default => !$z,
    };
    switch ($x) {
        !case 1:
        !case 2:
            $x;
        !default:
            $x;
    !}
}
interface Foo {
    public function bar();
}
CODE;

        $instrumentor = new Instrumentor(
            'InstrumentationContext', PhpVersion::getNewestSupported());
        $fileInfo = new FileInfo();
        $output = $instrumentor->instrument($input, $fileInfo);
        $this->assertSame($expected, $output);

        // The number of lines should be preserved.
        $inputNewlines = substr_count($input, "\n");
        $outputNewlines = substr_count($output, "\n");
        $this->assertSame($inputNewlines, $outputNewlines);

        $str = new MutableString($input);
        foreach ($fileInfo->blockIndexToPos as $pos) {
            $str->insert($pos, '!');
        }
        $this->assertSame($expectedCoverage, $str->getModifiedString());
    }
}
