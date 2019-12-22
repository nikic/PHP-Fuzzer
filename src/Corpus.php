<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\RNG;

final class Corpus {
    /** @var CorpusEntry[] */
    private array $entries = [];
    private array $seenEdgeCounts = [];

    public function isInteresting(CorpusEntry $entry): bool {
        if ($entry->crashInfo) {
            // Crashes are always interesting for now.
            return true;
        }

        // Check if we saw any new edges.
        foreach ($entry->edgeCounts as $edge => $count) {
            if (!isset($this->seenEdgeCounts[$this->encodeEdgeCount($edge, $count)])) {
                return true;
            }
        }
        return false;
    }

    public function addEntry(CorpusEntry $entry): void {
        $this->entries[] = $entry;
        foreach ($entry->edgeCounts as $edge => $count) {
            $this->seenEdgeCounts[$this->encodeEdgeCount($edge, $count)] = true;
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

    private function encodeEdgeCount(int $edge, int $count): int {
        if ($count < 4) {
            $encodedCount = $count - 1;
        } else if ($count < 8) {
            $encodedCount = 3;
        } else if ($count < 16) {
            $encodedCount = 4;
        } else if ($count < 32) {
            $encodedCount = 5;
        } else if ($count < 128) {
            $encodedCount = 6;
        } else {
            $encodedCount = 7;
        }
        return $encodedCount << 56 | $edge;
    }

    public function getNumCorpusEntries(): int {
        return \count($this->entries);
    }

    public function getNumFeatures(): int {
        return \count($this->seenEdgeCounts);
    }

    public function getSeenBlockMap(): array {
        $blocks = [];
        foreach ($this->seenEdgeCounts as $encodedEdgeCount => $_) {
            $targetBlock = $encodedEdgeCount & ((1 << 28) - 1);
            $blocks[$targetBlock] = true;
        }
        return $blocks;
    }
}