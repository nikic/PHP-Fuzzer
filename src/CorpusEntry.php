<?php declare(strict_types=1);

namespace PhpFuzzer;

final class CorpusEntry {
    public string $input;
    public array $features;
    public ?string $crashInfo;
    public ?string $path;
    public array $uniqueFeatures;

    public function __construct(string $input, array $features, ?string $crashInfo) {
        $this->input = $input;
        $this->features = $features;
        $this->crashInfo = $crashInfo;
        $this->path = null;
    }

    public function hasAllUniqueFeaturesOf(CorpusEntry $other): bool {
        foreach ($other->uniqueFeatures as $feature => $_) {
            if (!isset($this->features[$feature])) {
                return false;
            }
        }
        return true;
    }
}