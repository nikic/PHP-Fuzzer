<?php declare(strict_types=1);

use PhpFuzzer\Fuzzer;

error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$fuzzer = new Fuzzer();
$fuzzer->setCorpusDir(__DIR__ . '/corpus');
$fuzzer->setCoverageDir(__DIR__ . '/coverage');
$fuzzer->addDictionary(__DIR__ . '/php.dict');
$fuzzer->addInstrumentedDir(__DIR__ . '/../../tolerant-php-parser/src');
$fuzzer->startInstrumentation();

// Using tolerant-php-parser here, because the instrumentation uses php-parser,
// so we can't easily fuzz php-parser itself.
require __DIR__ . '/../../tolerant-php-parser/vendor/autoload.php';
$parser = new Microsoft\PhpParser\Parser();
$fuzzer->setTarget(function(string $input) use($parser) {
    if (\strlen($input) > 1024) {
        return;
    }
    $parser->parseSourceFile($input);
});

$fuzzer->handleCliArgs();
//$fuzzer->renderCoverage();
