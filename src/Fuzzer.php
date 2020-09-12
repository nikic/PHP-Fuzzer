<?php declare(strict_types=1);

namespace PhpFuzzer;

use GetOpt\ArgumentException;
use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Operand;
use GetOpt\Option;
use Nikic\IncludeInterceptor\FileFilter;
use Nikic\IncludeInterceptor\Interceptor;
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
    private string $outputDir;
    private Mutator $mutator;
    private RNG $rng;
    private Dictionary $dictionary;
    private \Closure $target;
    public ?string $targetPath = null;

    private ?string $coverageDir = null;
    private array $fileInfos = [];

    private int $runs = 0;
    private int $lastInterestingRun = 0;
    private int $initialFeatures;
    private float $startTime;
    private int $mutationDepthLimit = 5;
    private int $maxRuns = PHP_INT_MAX;
    private int $maxLen = PHP_INT_MAX;
    private int $lenControlFactor = 200;
    private int $timeout = 3;

    // Counts all crashes, including duplicates
    private int $crashes = 0;
    private int $maxCrashes = 100;

    public function __construct() {
        $this->outputDir = getcwd();
        $this->instrumentor = new Instrumentor(FuzzingContext::class);
        $this->rng = new RNG();
        $this->dictionary = new Dictionary();
        $this->mutator = new Mutator($this->rng, $this->dictionary);
        $this->corpus = new Corpus();

        // Instrument everything apart from our src/ directory.
        $fileFilter = FileFilter::createAllWhitelisted();
        $fileFilter->addBlackList(__DIR__);
        // Only intercept file:// streams. Interception of phar:// streams may run into
        // incorrect stat() handling during path resolution in PHP.
        $protocols = ['file'];
        $this->interceptor = new Interceptor(function(string $path) use($fileFilter) {
            if (!$fileFilter->test($path)) {
                return null;
            }

            $code = file_get_contents($path);
            $fileInfo = new FileInfo();
            $instrumentedCode = $this->instrumentor->instrument($code, $fileInfo);
            $this->fileInfos[$path] = $fileInfo;
            return $instrumentedCode;
        }, $protocols);
    }

    private function loadTarget(string $path): void {
        if (!is_file($path)) {
            throw new \Exception('Target "' . $path . '" does not exist');
        }

        $this->targetPath = $path;
        $this->startInstrumentation();
        // Unbind $this and make it available as $fuzzer variable.
        (static function(Fuzzer $fuzzer) use($path) {
            require $path;
        })($this);
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

    public function setMaxLen(int $maxLen): void {
        $this->maxLen = $maxLen;
    }

    public function startInstrumentation(): void {
        $this->interceptor->setUp();
    }

    public function fuzz(): void {
        if (!$this->loadCorpus()) {
            return;
        }

        // Start with a short maximum length, increase if we fail to make progress.
        $maxLen = min($this->maxLen, max(4, $this->corpus->getMaxLen()));

        // Don't count runs while loading the corpus.
        $this->runs = 0;
        $this->startTime = microtime(true);
        while ($this->runs < $this->maxRuns) {
            $origEntry = $this->corpus->getRandomEntry($this->rng);
            $input = $origEntry !== null ? $origEntry->input : "";
            $crossOverEntry = $this->corpus->getRandomEntry($this->rng);
            $crossOverInput = $crossOverEntry !== null ? $crossOverEntry->input : null;
            for ($m = 0; $m < $this->mutationDepthLimit; $m++) {
                $input = $this->mutator->mutate($input, $maxLen, $crossOverInput);
                $entry = $this->runInput($input);
                if ($entry->crashInfo) {
                    if ($this->corpus->addCrashEntry($entry)) {
                        $entry->storeAtPath($this->outputDir . '/crash-' . $entry->hash . '.txt');
                        $this->printCrash('CRASH', $entry);
                    } else {
                        echo "DUPLICATE CRASH\n";
                    }
                    if (++$this->crashes >= $this->maxCrashes) {
                        echo "Maximum of {$this->maxCrashes} crashes reached, aborting\n";
                        return;
                    }
                    break;
                }

                $this->corpus->computeUniqueFeatures($entry);
                if ($entry->uniqueFeatures) {
                    $this->corpus->addEntry($entry);
                    $entry->storeAtPath($this->corpusDir . '/' . $entry->hash . '.txt');

                    $this->lastInterestingRun = $this->runs;
                    $this->printAction('NEW', $entry);
                    break;
                }

                if ($origEntry !== null &&
                    \strlen($entry->input) < \strlen($origEntry->input) &&
                    $entry->hasAllUniqueFeaturesOf($origEntry)
                ) {
                    // Preserve unique features of original entry,
                    // even if they are not unique anymore at this point.
                    $entry->uniqueFeatures = $origEntry->uniqueFeatures;
                    if ($this->corpus->replaceEntry($origEntry, $entry)) {
                        $entry->storeAtPath($this->corpusDir . '/' . $entry->hash . '.txt');
                    }
                    unlink($origEntry->path);

                    $this->lastInterestingRun = $this->runs;
                    $this->printAction('REDUCE', $entry);
                    break;
                }
            }

            if ($maxLen < $this->maxLen) {
                // Increase max length if we haven't made progress in a while.
                $logMaxLen = (int) log($maxLen, 2);
                if (($this->runs - $this->lastInterestingRun) > $this->lenControlFactor * $logMaxLen) {
                    $maxLen = min($this->maxLen, $maxLen + $logMaxLen);
                    $this->lastInterestingRun = $this->runs;
                }
            }
        }
    }

    private function runInput(string $input) {
        $this->runs++;
        if (\extension_loaded('pcntl')) {
            \pcntl_alarm($this->timeout);
        }
        FuzzingContext::reset();
        $crashInfo = null;
        try {
            ($this->target)($input);
        } catch (\Exception $e) {
            // Assume that exceptions are not an abnormal conditions.
        } catch (\ParseError $e) {
            echo "PARSE ERROR $e\n";
            echo "INSTRUMENTATION BROKEN? -- ABORTING";
            exit(-1);
        } catch (\Error $e) {
            $crashInfo = (string) $e;
        }

        $features = $this->edgeCountsToFeatures(FuzzingContext::$edges);
        return new CorpusEntry($input, $features, $crashInfo);
    }

    private function edgeCountsToFeatures(array $edgeCounts): array {
        $features = [];
        foreach ($edgeCounts as $edge => $count) {
            $feature = $this->edgeCountToFeature($edge, $count);
            $features[$feature] = true;
        }
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
        $entries = [];
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $input = file_get_contents($path);
            $entry = $this->runInput($input);
            $entry->path = $path;
            if ($entry->crashInfo) {
                $this->printCrash("CORPUS CRASH", $entry);
                return false;
            }

            $entries[] = $entry;
        }

        // Favor short entries.
        usort($entries, function (CorpusEntry $a, CorpusEntry $b) {
            return \strlen($a->input) <=> \strlen($b->input);
        });
        foreach ($entries as $entry) {
            $this->corpus->computeUniqueFeatures($entry);
            if ($entry->uniqueFeatures) {
                $this->corpus->addEntry($entry);
            }
        }
        $this->initialFeatures = $this->corpus->getNumFeatures();
        return true;
    }

    private function printAction(string $action, CorpusEntry $entry) {
        $time = microtime(true) - $this->startTime;
        $mem = memory_get_usage();
        $numFeatures = $this->corpus->getNumFeatures();
        $numNewFeatures = $numFeatures - $this->initialFeatures;
        $maxLen = $this->corpus->getMaxLen();
        $maxLenLen = \strlen((string) $maxLen);
        echo sprintf(
            "%-6s run: %d (%4.0f/s), ft: %d (%.0f/s), corp: %d (%s), len: %{$maxLenLen}d/%d, t: %.0fs, mem: %s\n",
            $action, $this->runs, $this->runs / $time,
            $numFeatures, $numNewFeatures / $time,
            $this->corpus->getNumCorpusEntries(),
            $this->formatBytes($this->corpus->getTotalLen()),
            \strlen($entry->input), $maxLen,
            $time, $this->formatBytes($mem));
    }

    private function formatBytes(int $bytes): string {
        if ($bytes < 10 * 1024) {
            return $bytes . 'b';
        } else if ($bytes < 10 * 1024 * 1024) {
            $kiloBytes = (int) round($bytes / 1024);
            return $kiloBytes . 'kb';
        } else {
            $megaBytes = (int) round($bytes / (1024 * 1024));
            return $megaBytes . 'mb';
        }
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

        $input = file_get_contents($path);
        $entry = $this->runInput($input);
        if (!$entry->crashInfo) {
            throw new \Exception("Crash input did not crash");
        }

        while ($this->runs < $this->maxRuns) {
            // TODO: Mutation depth, etc.
            $newInput = $this->mutator->mutate($input, $this->maxLen, null);
            if (\strlen($newInput) >= \strlen($input)) {
                continue;
            }

            $newEntry = $this->runInput($newInput);
            if (!$newEntry->crashInfo) {
                continue;
            }

            $entry->storeAtPath(getcwd() . '/minimized-' . md5($newInput) . '.txt');

            $len = \strlen($newInput);
            $this->printCrash("CRASH with length $len", $entry);
            $input = $newInput;
        }
    }

    public function handleCliArgs() {
        $getOpt = new GetOpt([
            Option::create('h', 'help', GetOpt::NO_ARGUMENT)
                ->setDescription('Display this help'),
            Option::create(null, 'dict', GetOpt::REQUIRED_ARGUMENT)
                ->setArgumentName('file')
                ->setDescription('Use dictionary file'),
            Option::create(null, 'max-runs', GetOpt::REQUIRED_ARGUMENT)
                ->setArgumentName('num')
                ->setDescription('Limit maximum target executions'),
            Option::create(null, 'timeout', GetOpt::REQUIRED_ARGUMENT)
                ->setArgumentName('seconds')
                ->setDescription('Timeout for one target execution'),
            Option::create(null, 'len-control-factor', GetOpt::REQUIRED_ARGUMENT)
                ->setArgumentName('num')
                ->setDescription('A higher value will increase the maximum length more slowly'),
        ]);
        $getOpt->addOperand(Operand::create('target', Operand::REQUIRED));

        $getOpt->addCommand(Command::create('fuzz', [$this, 'handleFuzzCommand'])
            ->addOperand(Operand::create('corpus', Operand::OPTIONAL))
            ->setDescription('Fuzz the target to find bugs'));
        $getOpt->addCommand(Command::create('minimize-crash', [$this, 'handleMinimizeCrashCommand'])
            ->addOperand(Operand::create('input', Operand::REQUIRED))
            ->setDescription('Reduce the size of a crashing input'));
        $getOpt->addCommand(Command::create('run-single', [$this, 'handleRunSingleCommand'])
            ->addOperand(Operand::create('input', Operand::REQUIRED))
            ->setDescription('Run single input through target'));
        $getOpt->addCommand(Command::create('report-coverage', [$this, 'handleReportCoverage'])
            ->addOperand(Operand::create('corpus', Operand::REQUIRED))
            ->addOperand(Operand::create('coverage-dir', Operand::REQUIRED))
            ->setDescription('Generate a HTML coverage report'));

        try {
            $getOpt->process();
        } catch (ArgumentException $e) {
            echo $e->getMessage() . PHP_EOL;
            echo PHP_EOL . $getOpt->getHelpText();
            return;
        }

        if ($getOpt->getOption('help')) {
            echo $getOpt->getHelpText();
            return;
        }

        $command = $getOpt->getCommand();
        if (!$command) {
            echo 'Missing command' . PHP_EOL;
            echo PHP_EOL . $getOpt->getHelpText();
            return;
        }

        $opts = $getOpt->getOptions();
        if (isset($opts['max-runs'])) {
            $this->maxRuns = (int) $opts['max-runs'];
        }
        if (isset($opts['timeout'])) {
            $this->timeout = (int) $opts['timeout'];
        }
        if (isset($opts['len-control-factor'])) {
            $this->lenControlFactor = (int) $opts['len-control-factor'];
        }
        if (isset($opts['dict'])) {
            $this->addDictionary($opts['dict']);
        }

        $this->setupTimeoutHandler();
        $this->setupErrorHandler();
        $this->loadTarget($getOpt->getOperand('target'));

        $command->getHandler()($getOpt);
    }

    private function createTemporaryCorpusDirectory(): string {
        do {
            $corpusDir = sys_get_temp_dir(). '/corpus-' . mt_rand();
        } while (file_exists($corpusDir));
        if (!@mkdir($corpusDir)) {
            throw new \Exception("Failed to create temporary corpus directory $corpusDir");
        }
        return $corpusDir;
    }

    private function handleFuzzCommand(GetOpt $getOpt) {
        $corpusDir = $getOpt->getOperand('corpus');
        if ($corpusDir === null) {
            $corpusDir = $this->createTemporaryCorpusDirectory();
            echo "Using $corpusDir as corpus directory\n";
        }
        $this->setCorpusDir($corpusDir);
        $this->fuzz();
    }

    private function handleRunSingleCommand(GetOpt $getOpt) {
        $inputPath = $getOpt->getOperand('input');
        if (!is_file($inputPath)) {
            throw new \Exception('Input "' . $inputPath . '" does not exist');
        }

        $input = file_get_contents($inputPath);
        $entry = $this->runInput($input);
        $entry->path = $inputPath;
        if ($entry->crashInfo) {
            $this->printCrash('CRASH', $entry);
        }
    }

    private function handleMinimizeCrashCommand(GetOpt $getOpt) {
        if ($this->maxRuns === PHP_INT_MAX) {
            $this->maxRuns = 100000;
        }
        $this->minimizeCrash($getOpt->getOperand('input'));
    }

    private function handleReportCoverage(GetOpt $getOpt) {
        $this->setCorpusDir($getOpt->getOperand('corpus'));
        $this->setCoverageDir($getOpt->getOperand('coverage-dir'));
        $this->loadCorpus();
        $this->renderCoverage();
    }

    private function setupTimeoutHandler(): void {
        if (\extension_loaded('pcntl')) {
            \pcntl_signal(SIGALRM, function() {
                throw new \Error("Timeout of {$this->timeout} seconds exceeded");
            });
            \pcntl_async_signals(true);
        }
    }

    private function setupErrorHandler(): void {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return true;
            }

            throw new \Error(sprintf(
                '[%d] %s in %s on line %d', $errno, $errstr, $errfile, $errline));
        });
    }
}
