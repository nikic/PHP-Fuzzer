<?php declare(strict_types=1);

use PhpFuzzer\Fuzzer;

require __DIR__ . '/../vendor/autoload.php';

$fuzzer = new Fuzzer();
$fuzzer->setCorpusDir(__DIR__ . '/corpus');
$fuzzer->addInstrumentedDir(__DIR__);
$fuzzer->startInstrumentation();

// TODO: Fuzzing php-parser is more complicated, because we use it ourselves.
// $parser = new \PhpParser\Parser\Php7(new \PhpParser\Lexer());

require __DIR__ . '/fuzzing_target.php';
$fuzzer->fuzz(function(string $input) use($parser) {
    fuzzingTarget1($input);
});

