<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

final class Context {
    public string $runtimeContextName;
    private int $blockIndex = 0;

    public function __construct(string $runtimeContextName) {
        $this->runtimeContextName = $runtimeContextName;
    }

    public function getNewBlockIndex(): int {
        return $this->blockIndex++;
    }
}