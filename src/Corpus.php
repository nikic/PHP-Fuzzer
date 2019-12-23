<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\RNG;

final class Corpus {
    /** @var CorpusEntry[] */
    private array $entries = [];
    private array $seenFeatures = [];

    public function computeUniqueFeatures(CorpusEntry $entry) {
        $entry->uniqueFeatures = [];
        foreach ($entry->features as $feature => $_) {
            if (!isset($this->seenFeatures[$feature])) {
                $entry->uniqueFeatures[$feature] = true;
            }
        }
    }

    public function addEntry(CorpusEntry $entry): void {
        $this->entries[] = $entry;
        foreach ($entry->uniqueFeatures as $feature => $_) {
            $this->seenFeatures[$feature] = true;
        }
    }

    public function replaceEntry(CorpusEntry $origEntry, CorpusEntry $newEntry): void {
        $idx = array_search($origEntry, $this->entries); // TODO: Optimize
        $this->entries[$idx] = $newEntry;
    }

    public function getRandomEntry(RNG $rng): ?CorpusEntry {
        if (empty($this->entries)) {
            return null;
        }

        return $rng->randomElement($this->entries);
    }

    public function getNumCorpusEntries(): int {
        return \count($this->entries);
    }

    public function getNumFeatures(): int {
        return \count($this->seenFeatures);
    }

    public function getSeenBlockMap(): array {
        $blocks = [];
        foreach ($this->seenFeatures as $feature => $_) {
            $targetBlock = $feature & ((1 << 28) - 1);
            $blocks[$targetBlock] = true;
        }
        return $blocks;
    }
}