<?php declare(strict_types=1);

namespace PhpFuzzer;

use Icewind\Interceptor\Interceptor;
use PhpFuzzer\Instrumentation\Instrumentor;
use PhpFuzzer\Mutation\Mutator;
use PhpFuzzer\Mutation\RNG;

final class Fuzzer {
    private Interceptor $interceptor;
    private Instrumentor $instrumentor;
    private Corpus $corpus;
    private string $corpusDir;
    private Mutator $mutator;
    private RNG $rng;

    public function __construct() {
        // TODO: Cache instrumented files?
        // TODO: Support "external instrumentation" to allow fuzzing php-parser.
        $this->instrumentor = new Instrumentor(FuzzingContext::class);
        $this->rng = new RNG();
        $this->mutator = new Mutator($this->rng);
        $this->corpus = new Corpus();
        $this->interceptor = new Interceptor();
        $this->interceptor->addHook(function($code) {
            return $this->instrumentor->instrument($code);
        });
    }

    public function setCorpusDir(string $path): void {
        $this->corpusDir = $path;
        if (!is_dir($this->corpusDir)) {
            throw new \Exception('Corpus directory "' . $this->corpusDir . '" does not exist');
        }
    }

    public function addInstrumentedDir(string $path): void {
        $this->interceptor->addWhiteList(realpath($path));
    }

    public function startInstrumentation(): void {
        $this->interceptor->setUp();
    }

    public function fuzz(\Closure $target): void {
        if (!$this->loadCorpus($target)) {
            return;
        }

        $mutationDepthLimit = 5;
        for ($i = 0; $i < 10000; $i++) {
            $input = $this->corpus->getRandomInput($this->rng) ?? "";
            for ($m = 0; $m < $mutationDepthLimit; $m++) {
                $input = $this->mutator->mutate($input);
                $entry = $this->runTarget($target, $input);
                if ($this->corpus->isInteresting($entry)) {
                    $this->corpus->addEntry($entry);

                    $entry->path = $this->corpusDir . '/' . md5($entry->input) . '.txt';
                    file_put_contents($entry->path, $entry->input);

                    if ($entry->crashInfo) {
                        $this->printCrash("CRASH", $entry);
                        return;
                    }
                }
            }
        }
    }

    private function runTarget(\Closure $target, string $input) {
        FuzzingContext::reset();
        $crashInfo = null;
        try {
            $target($input);
        } catch (\Exception $e) {
            // Assume that exceptions are not an abnormal condition.
        } catch (\Error $e) {
            $crashInfo = (string) $e;
        }
        return new CorpusEntry($input, FuzzingContext::$edges, $crashInfo);
    }

    private function loadCorpus(\Closure $target): bool {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->corpusDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $input = file_get_contents($path);
            $entry = $this->runTarget($target, $input);
            if ($this->corpus->isInteresting($entry)) {
                $entry->path = $path;
                $this->corpus->addEntry($entry);
                if ($entry->crashInfo) {
                    $this->printCrash("CORPUS CRASH", $entry);
                    return false;
                }
            }
        }
        return true;
    }

    private function printCrash(string $prefix, CorpusEntry $entry) {
        echo "$prefix in $entry->path!\n";
        echo $entry->crashInfo . "\n";
    }
}