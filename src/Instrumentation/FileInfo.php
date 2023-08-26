<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

final class FileInfo {
    /** @var array<int, int> */
    public array $blockIndexToPos = [];
}
