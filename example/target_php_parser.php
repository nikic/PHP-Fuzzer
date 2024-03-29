<?php declare(strict_types=1);

/** @var PhpFuzzer\Config $config */

if (class_exists(PhpParser\Parser\Php7::class)) {
    echo "The PHP-Parser target can only be used with php-fuzzer.phar,\n";
    echo "otherwise there is a conflict with php-fuzzer's own use of PHP-Parser.\n";
    exit(1);
}

$autoload = __DIR__ . '/PHP-Parser/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Cannot find PHP-Parser installation in " . __DIR__ . "/PHP-Parser\n";
    exit(1);
}

require $autoload;

$parser = new PhpParser\Parser\Php7(new PhpParser\Lexer);
$prettyPrinter = new PhpParser\PrettyPrinter\Standard();

$config->setTarget(function(string $input) use($parser, $prettyPrinter) {
    $stmts = $parser->parse($input);
    $prettyPrinter->prettyPrintFile($stmts);
});

$config->setMaxLen(1024);
$config->addDictionary(__DIR__ . '/php.dict');
$config->setAllowedExceptions([PhpParser\Error::class]);
