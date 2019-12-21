<?php declare(strict_types=1);

use PhpFuzzer\Fuzzer;

error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$fuzzer = new Fuzzer();
$fuzzer->setCorpusDir(__DIR__ . '/corpus');
$fuzzer->addDictionary(__DIR__ . '/php.dict');
$fuzzer->addInstrumentedDir(__DIR__ . '/../../tolerant-php-parser/src');
$fuzzer->startInstrumentation();

require __DIR__ . '/../../tolerant-php-parser/vendor/autoload.php';
$parser = new Microsoft\PhpParser\Parser();
$fuzzer->fuzz(function(string $input) use($parser) {
    if (\strlen($input) > 1024) {
        return;
    }
    $parser->parseSourceFile($input);
});

