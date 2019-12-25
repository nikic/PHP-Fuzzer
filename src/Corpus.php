<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\RNG;

final class Corpus {
    /** @var CorpusEntry[] */
    private array $entries = [];
    private array $seenFeatures = [];

    /** @var CorpusEntry[] $crashEntries */
    private array $crashEntries = [];
    private array $seenCrashFeatures = [];

    private int $totalLen = 0;
    private int $maxLen = 0;

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
        $len = \strlen($entry->input);
        $this->totalLen += $len;
        $this->maxLen = max($this->maxLen, $len);
    }

    public function replaceEntry(CorpusEntry $origEntry, CorpusEntry $newEntry): void {
        $idx = array_search($origEntry, $this->entries); // TODO: Optimize
        $this->entries[$idx] = $newEntry;
        $this->totalLen -= \strlen($origEntry->input);
        $this->totalLen += \strlen($newEntry->input);
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

    public function getTotalLen(): int {
        return $this->totalLen;
    }

    public function getMaxLen(): int {
        return $this->maxLen;
    }

    public function getSeenBlockMap(): array {
        $blocks = [];
        foreach ($this->seenFeatures as $feature => $_) {
            $targetBlock = $feature & ((1 << 28) - 1);
            $blocks[$targetBlock] = true;
        }
        return $blocks;
    }

    public function addCrashEntry(CorpusEntry $entry): bool {
        // TODO: Also handle "absent feature"?
        $hasNewFeature = false;
        foreach ($entry->features as $feature => $_) {
            if (!isset($this->seenCrashFeatures[$feature])) {
                $hasNewFeature = true;
                $this->seenCrashFeatures[$feature] = true;
            }
        }
        if ($hasNewFeature) {
            $this->crashEntries[] = $entry;
            return true;
        }
        return false;
    }
}