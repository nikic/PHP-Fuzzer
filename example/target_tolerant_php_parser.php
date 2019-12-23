<?php declare(strict_types=1);

// Using tolerant-php-parser here, because the instrumentation uses php-parser,
// so we can't easily fuzz php-parser itself.

/** @var PhpFuzzer\Fuzzer $fuzzer */

$fuzzer->addDictionary(__DIR__ . '/php.dict');
$fuzzer->addInstrumentedDir(__DIR__ . '/../../tolerant-php-parser/src');

require __DIR__ . '/../../tolerant-php-parser/vendor/autoload.php';
$parser = new Microsoft\PhpParser\Parser();

$fuzzer->setTarget(function(string $input) use($parser) {
    if (\strlen($input) > 1024) {
        return;
    }
    $parser->parseSourceFile($input);
});
