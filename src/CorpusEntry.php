<?php declare(strict_types=1);

namespace PhpFuzzer;

final class CorpusEntry {
    public string $input;
    public string $hash;
    public array $features;
    public ?string $crashInfo;
    public ?string $path;
    public array $uniqueFeatures;

    public function __construct(string $input, array $features, ?string $crashInfo) {
        $this->input = $input;
        $this->hash = \md5($input);
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

    public function storeAtPath(string $path): void {
        assert($this->path === null);
        $this->path = $path;
        $result = file_put_contents($this->path, $this->input);
        assert($result === \strlen($this->input));
    }
}