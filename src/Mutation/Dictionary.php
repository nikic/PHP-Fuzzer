<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

final class Dictionary {
    public array $dict = [];

    public function isEmpty(): bool {
        return empty($this->dict);
    }

    public function addWords(array $words): void {
        $this->dict = [...$this->dict, ...$words];
    }
}