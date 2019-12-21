<?php declare(strict_types=1);

namespace PhpFuzzer;

final class CorpusEntry {
    public string $input;
    public array $edgeCounts;
    public ?string $path;

    public function __construct(string $input, array $edgeCounts) {
        $this->input = $input;
        $this->edgeCounts = $edgeCounts;
        $this->path = null;
    }
}