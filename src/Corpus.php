<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\RNG;

final class Corpus {
    private string $dir;
    /** @var CorpusEntry[] */
    private array $entries = [];
    private array $seenEdges = [];

    public function __construct(string $dir) {
        $this->dir = $dir;
        if (!is_dir($this->dir)) {
            throw new \Exception('Corpus directory "' . $this->dir . '" does not exist');
        }
    }

    public function loadCorpus(): void {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                // TODO: Wrong place.
            }
        }
    }

    public function addInput(string $input, array $edgeCounts): void {
        $entry = new CorpusEntry($input, $edgeCounts);

        $isInteresting = false;
        foreach ($entry->edgeCounts as $edge => $_count) {
            if (!isset($this->seenEdges[$edge])) {
                $isInteresting = true;
                $this->seenEdges[$edge] = true;
            }
        }

        if ($isInteresting) {
            $this->addEntry($entry);
        }
    }

    private function addEntry(CorpusEntry $entry) {
        $this->entries[] = $entry;
        $entry->path = $this->dir . '/' . md5($entry->input) . '.txt';
        file_put_contents($entry->path, $entry->input);
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