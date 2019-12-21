<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

final class Context {
    public string $runtimeContextName;
    // We reserve 0 as the global entry point, start counting from 1.
    private int $blockIndex = 1;

    public function __construct(string $runtimeContextName) {
        $this->runtimeContextName = $runtimeContextName;
    }

    public function getNewBlockIndex(): int {
        return $this->blockIndex++;
    }
}