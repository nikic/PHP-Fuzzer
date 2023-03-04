<?php declare(strict_types=1);

/** @var PhpFuzzer\Config $config */

$autoload = __DIR__ . '/PHP-CSS-Parser/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Cannot find PHP-CSS-Parser installation in " . __DIR__ . "/PHP-CSS_Parser\n";
    exit(1);
}

require $autoload;

$config->setTarget(function(string $input) {
    $parser = new Sabberworm\CSS\Parser($input);
    $parser->parse();
});
