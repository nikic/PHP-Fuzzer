<?php declare(strict_types=1);

/** @var PhpFuzzer\Fuzzer $fuzzer */

$autoload = __DIR__ . '/tolerant-php-parser/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Cannot find tolerant-php-parser installation in " . __DIR__ . "/tolerant-php-parser\n";
    exit(1);
}

require $autoload;

$parser = new Microsoft\PhpParser\Parser();

$fuzzer->setTarget(function(string $input) use($parser) {
    $parser->parseSourceFile($input);
});

$fuzzer->setMaxLen(1024);
$fuzzer->addDictionary(__DIR__ . '/php.dict');