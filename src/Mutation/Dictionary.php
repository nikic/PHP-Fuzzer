<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

final class Dictionary {
    /** @var list<string> */
    public array $dict = [];

    public function isEmpty(): bool {
        return empty($this->dict);
    }

    /**
     * @param list<string> $words
     */
    public function addWords(array $words): void {
        $this->dict = [...$this->dict, ...$words];
    }
}
