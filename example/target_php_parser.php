<?php declare(strict_types=1);

// This only works with php-fuzzer.phar, which uses a prefixed version of php-parser!

/** @var PhpFuzzer\Fuzzer $fuzzer */

$fuzzer->addDictionary(__DIR__ . '/php.dict');
$fuzzer->addInstrumentedDir(__DIR__ . '/../../PHP-Parser/lib');

require __DIR__ . '/../../PHP-Parser/vendor/autoload.php';
$parser = new PhpParser\Parser\Php7(new PhpParser\Lexer);
$prettyPrinter = new PhpParser\PrettyPrinter\Standard();

$fuzzer->setTarget(function(string $input) use($parser, $prettyPrinter) {
    if (\strlen($input) > 1024) {
        return;
    }
    $stmts = $parser->parse($input);
    $prettyPrinter->prettyPrintFile($stmts);
});
