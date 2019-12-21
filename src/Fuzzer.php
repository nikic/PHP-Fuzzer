<?php declare(strict_types=1);

namespace PhpFuzzer;

use Icewind\Interceptor\Interceptor;
use PhpFuzzer\Instrumentation\Instrumentor;

final class Fuzzer {
    private Interceptor $interceptor;
    private Instrumentor $instrumentor;

    public function __construct() {
        // TODO: Cache instrumented files?
        // TODO: Support "external instrumentation" to allow fuzzing php-parser.
        $this->instrumentor = new Instrumentor(FuzzingContext::class);
        $this->interceptor = new Interceptor();
        $this->interceptor->addHook(function($code) {
            return $this->instrumentor->instrument($code);
        });
    }

    public function addInstrumentedDir(string $path): void {
        $this->interceptor->addWhiteList(realpath($path));
    }

    public function startInstrumentation(): void {
        $this->interceptor->setUp();
    }

    public function fuzz(\Closure $target): void {
        FuzzingContext::reset();
        $target("Test");
        var_dump(FuzzingContext::$edges);
    }
}