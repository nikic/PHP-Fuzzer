<?php declare(strict_types=1);

use PhpFuzzer\Fuzzer;

error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$fuzzer = new Fuzzer();
$fuzzer->setCorpusDir(__DIR__ . '/corpus');
$fuzzer->addInstrumentedDir(__DIR__);
$fuzzer->startInstrumentation();

require __DIR__ . '/fuzzing_target.php';
$fuzzer->setTarget(function(string $input) {
    fuzzingTarget1($input);
});
$fuzzer->handleCliArgs();

