<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\RNG;

final class Corpus {
    /** @var CorpusEntry[] */
    private array $entries = [];
    private array $seenEdges = [];

    public function isInteresting(CorpusEntry $entry): bool {
        if ($entry->crashInfo) {
            // Crashes are always interesting for now.
            return true;
        }

        // Check if we saw any new edges.
        foreach ($entry->edgeCounts as $edge => $_count) {
            if (!isset($this->seenEdges[$edge])) {
                return true;
            }
        }
        return false;
    }

    public function addEntry(CorpusEntry $entry) {
        $this->entries[] = $entry;
        foreach ($entry->edgeCounts as $edge => $_count) {
            $this->seenEdges[$edge] = true;
        }
    }

    // TODO: This doesn't really belong here?
    public function getRandomInput(RNG $rng): ?string {
        if (empty($this->entries)) {
            return null;
        }

        $entry = $this->entries[$rng->randomInt(0, count($this->entries) - 1)];
        return $entry->input;
    }
}