<?php declare(strict_types=1);

use PhpFuzzer\Fuzzer;

error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../../tolerant-php-parser/vendor/autoload.php';

$fuzzer = new Fuzzer();
$fuzzer->setCorpusDir(__DIR__ . '/corpus');
$fuzzer->addInstrumentedDir(__DIR__ . '/../../tolerant-php-parser/src');
$fuzzer->startInstrumentation();

$parser = new Microsoft\PhpParser\Parser();
$fuzzer->fuzz(function(string $input) use($parser) {
    $parser->parseSourceFile($input);
});

