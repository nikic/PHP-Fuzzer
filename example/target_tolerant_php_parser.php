<?php declare(strict_types=1);

/** @var PhpFuzzer\Config $config */

$autoload = __DIR__ . '/tolerant-php-parser/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Cannot find tolerant-php-parser installation in " . __DIR__ . "/tolerant-php-parser\n";
    exit(1);
}

require $autoload;

$parser = new Microsoft\PhpParser\Parser();

$config->setTarget(function(string $input) use($parser) {
    $parser->parseSourceFile($input);
});

$config->setMaxLen(1024);
$config->addDictionary(__DIR__ . '/php.dict');
