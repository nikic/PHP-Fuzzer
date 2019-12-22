<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\RNG;

final class Corpus {
    /** @var CorpusEntry[] */
    private array $entries = [];
    private array $seenFeatures = [];

    public function isInteresting(CorpusEntry $entry): bool {
        if ($entry->crashInfo) {
            // Crashes are always interesting for now.
            return true;
        }

        // Check if we saw any new features.
        foreach ($entry->features as $feature) {
            if (!isset($this->seenFeatures[$feature])) {
                return true;
            }
        }
        return false;
    }

    public function addEntry(CorpusEntry $entry): void {
        $this->entries[] = $entry;
        foreach ($entry->features as $feature) {
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