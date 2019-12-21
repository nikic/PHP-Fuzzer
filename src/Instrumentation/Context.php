<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

final class Context {
    private int $blockIndex = 0;

    public function getNewBlockIndex(): int {
        return $this->blockIndex++;
    }
}