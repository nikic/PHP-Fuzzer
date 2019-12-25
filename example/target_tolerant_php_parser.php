<?php declare(strict_types=1);

// Using tolerant-php-parser here, because the instrumentation uses php-parser,
// so we can't easily fuzz php-parser itself.

/** @var PhpFuzzer\Fuzzer $fuzzer */

$fuzzer->setMaxLen(1024);
$fuzzer->addDictionary(__DIR__ . '/php.dict');

require __DIR__ . '/../../tolerant-php-parser/vendor/autoload.php';
$parser = new Microsoft\PhpParser\Parser();

$fuzzer->setTarget(function(string $input) use($parser) {
    $parser->parseSourceFile($input);
});
