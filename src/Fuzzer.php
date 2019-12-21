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
    private Mutator $mutator;
    private RNG $rng;

    public function __construct() {
        // TODO: Cache instrumented files?
        // TODO: Support "external instrumentation" to allow fuzzing php-parser.
        $this->instrumentor = new Instrumentor(FuzzingContext::class);
        $this->rng = new RNG();
        $this->mutator = new Mutator($this->rng);
        $this->interceptor = new Interceptor();
        $this->interceptor->addHook(function($code) {
            return $this->instrumentor->instrument($code);
        });
    }

    public function setCorpusDir(string $path): void {
        $this->corpus = new Corpus($path);
    }

    public function addInstrumentedDir(string $path): void {
        $this->interceptor->addWhiteList(realpath($path));
    }

    public function startInstrumentation(): void {
        $this->interceptor->setUp();
    }

    public function fuzz(\Closure $target): void {
        for ($i = 0; $i < 10000; $i++) {
            $input = $this->corpus->getRandomInput($this->rng) ?? "Test";
            $input = $this->mutator->mutate($input);

            FuzzingContext::reset();
            $isCrash = false;
            try {
                $target($input);
            } catch (\Exception $e) {
            } catch (\Error $e) {
                $isCrash = true;
            }

            $this->corpus->addInput($input, FuzzingContext::$edges);
            if ($isCrash) {
                echo "CRASH! " . $e . "\n";
                break;
            }
        }
    }
}