<?php declare(strict_types=1);

namespace PhpFuzzer;

use Icewind\Interceptor\Interceptor;
use PhpFuzzer\Instrumentation\FileInfo;
use PhpFuzzer\Instrumentation\Instrumentor;
use PhpFuzzer\Mutation\Dictionary;
use PhpFuzzer\Mutation\Mutator;
use PhpFuzzer\Mutation\RNG;

final class Fuzzer {
    private Interceptor $interceptor;
    private Instrumentor $instrumentor;
    private Corpus $corpus;
    private string $corpusDir;
    private Mutator $mutator;
    private RNG $rng;
    private Dictionary $dictionary;
    private \Closure $target;

    private ?string $coverageDir = null;
    private array $fileInfos = [];

    private int $mutationDepthLimit = 5;
    private int $maxRuns = 10000;

    public function __construct() {
        // TODO: Cache instrumented files?
        // TODO: Support "external instrumentation" to allow fuzzing php-parser.
        $this->instrumentor = new Instrumentor(FuzzingContext::class);
        $this->rng = new RNG();
        $this->dictionary = new Dictionary();
        $this->mutator = new Mutator($this->rng, $this->dictionary);
        $this->corpus = new Corpus();
        $this->interceptor = new Interceptor();

        $this->interceptor->addHook(function(string $code, string $path) {
            $fileInfo = new FileInfo();
            $instrumentedCode = $this->instrumentor->instrument($code, $fileInfo);
            if ($this->coverageDir !== null) {
                $this->fileInfos[$path] = $fileInfo;
            }
            return $instrumentedCode;
        });
    }

    public function setTarget(\Closure $target): void {
        $this->target = $target;
    }

    public function setCorpusDir(string $path): void {
        $this->corpusDir = $path;
        if (!is_dir($this->corpusDir)) {
            throw new \Exception('Corpus directory "' . $this->corpusDir . '" does not exist');
        }
    }

    public function setCoverageDir(string $path): void {
        $this->coverageDir = $path;
    }

    public function addDictionary(string $path): void {
        if (!is_file($path)) {
            throw new \Exception('Dictionary "' . $path . '" does not exist');
        }

        $parser = new DictionaryParser();
        $this->dictionary->addWords($parser->parse(file_get_contents($path)));
    }

    public function addInstrumentedDir(string $path): void {
        $this->interceptor->addWhiteList(realpath($path));
    }

    public function startInstrumentation(): void {
        $this->interceptor->setUp();
    }

    public function fuzz(): void {
        if (!$this->loadCorpus()) {
            return;
        }

        for ($i = 0; $i < $this->maxRuns; $i++) {
            $origEntry = $this->corpus->getRandomEntry($this->rng);
            $input = $origEntry !== null ? $origEntry->input : "";
            $crossOverEntry = $this->corpus->getRandomEntry($this->rng);
            $crossOverInput = $crossOverEntry !== null ? $crossOverEntry->input : null;
            for ($m = 0; $m < $this->mutationDepthLimit; $m++) {
                $input = $this->mutator->mutate($input, $crossOverInput);
                $entry = $this->runInput($input);
                if ($this->corpus->isInteresting($entry)) {
                    $this->corpus->addEntry($entry);

                    $entry->path = $this->corpusDir . '/' . md5($entry->input) . '.txt';
                    file_put_contents($entry->path, $entry->input);

                    $this->printAction('NEW', $i);
                    if ($entry->crashInfo) {
                        $this->printCrash('CRASH', $entry);
                        return;
                    }
                }

                // TODO: Use unique features instead of full features.
                if ($origEntry->features === $entry->features &&
                    \strlen($input) < \strlen($origEntry->input)
                ) {
                    $this->corpus->replaceEntry($origEntry, $entry);

                    // TODO: Refactor corpus storage.
                    $entry->path = $this->corpusDir . '/' . md5($entry->input) . '.txt';
                    file_put_contents($entry->path, $entry->input);
                    unlink($origEntry->path);
                    $this->printAction('REDUCE', $i);
                    break;
                }
            }
        }
    }

    private function runInput(string $input) {
        FuzzingContext::reset();
        $crashInfo = null;
        try {
            ($this->target)($input);
        } catch (\Exception $e) {
            // Assume that exceptions are not an abnormal conditions.
        } catch (\Error $e) {
            $crashInfo = (string) $e;
        }

        $features = $this->edgeCountsToFeatures(FuzzingContext::$edges);
        return new CorpusEntry($input, $features, $crashInfo);
    }

    private function edgeCountsToFeatures(array $edgeCounts): array {
        $featureMap = [];
        foreach ($edgeCounts as $edge => $count) {
            $feature = $this->edgeCountToFeature($edge, $count);
            $featureMap[$feature] = true;
        }

        $features = array_keys($featureMap);
        sort($features);
        return $features;
    }

    private function edgeCountToFeature(int $edge, int $count): int {
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

    private function loadCorpus(): bool {
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
            $entry = $this->runInput($input);
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

    private function printAction(string $action, int $run) {
        echo str_pad($action, 6, ' ') . " "
            . "run: $run, "
            . "ft: {$this->corpus->getNumFeatures()}, "
            . "corpus: {$this->corpus->getNumCorpusEntries()}\n";
    }

    private function printCrash(string $prefix, CorpusEntry $entry) {
        echo "$prefix in $entry->path!\n";
        echo $entry->crashInfo . "\n";
    }

    public function renderCoverage() {
        if ($this->coverageDir === null) {
            throw new \Exception('Missing coverage directory');
        }

        $renderer = new CoverageRenderer($this->coverageDir);
        $renderer->render($this->fileInfos, $this->corpus->getSeenBlockMap());
    }

    private function minimizeCrash(string $path) {
        if (!is_file($path)) {
            throw new \Exception("Crash input \"$path\" does not exist");
        }

        // TODO: realpath() works around a bug in interceptor!
        $input = file_get_contents(realpath($path));
        $entry = $this->runInput($input);
        if (!$entry->crashInfo) {
            throw new \Exception("Crash input did not crash");
        }

        for ($i = 0; $i < 10000; $i++) {
            // TODO: Mutation depth, etc.
            $newInput = $this->mutator->mutate($input, null);
            if (\strlen($newInput) >= \strlen($input)) {
                continue;
            }

            $newEntry = $this->runInput($newInput);
            if (!$newEntry->crashInfo) {
                continue;
            }

            $path = getcwd() . '/minimized-' . md5($newInput) . '.txt';
            file_put_contents($path, $newInput);

            $len = \strlen($newInput);
            echo "CRASH $len @ $path\n";
            $input = $newInput;
        }
    }

    public function runSingleInput(string $path) {
        $input = file_get_contents($path);
        $entry = $this->runInput($input);
        $entry->path = $path;
        if ($entry->crashInfo) {
            $this->printCrash('CRASH', $entry);
        }
    }

    public function handleCliArgs() {
        $shortOpts = '';
        $longOpts = ['minimize-crash:'];
        $opts = getopt($shortOpts, $longOpts, $optind);
        $rest = array_slice($GLOBALS['argv'], $optind);

        if (isset($opts['minimize-crash'])) {
            $this->minimizeCrash($opts['minimize-crash']);
            return;
        }

        if (!empty($rest)) {
            if (is_file($rest[0])) {
                echo "Running single input\n";
                $this->runSingleInput($rest[0]);
                return;
            }
        }

        $this->fuzz();
    }
}