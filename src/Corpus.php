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

    public function addEntry(CorpusEntry $entry) {
        $this->entries[] = $entry;
        foreach ($entry->edgeCounts as $edge => $count) {
            $this->seenEdgeCounts[$this->encodeEdgeCount($edge, $count)] = true;
        }
    }

    // TODO: This doesn't really belong here?
    public function getRandomInput(RNG $rng): ?string {
        if (empty($this->entries)) {
            return null;
        }

        $entry = $rng->randomElement($this->entries);
        return $entry->input;
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
}