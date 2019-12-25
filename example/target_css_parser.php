<?php declare(strict_types=1);

/** @var PhpFuzzer\Fuzzer $fuzzer */

require __DIR__ . '/PHP-CSS-Parser/vendor/autoload.php';

$fuzzer->setTarget(function(string $input) {
    $parser = new Sabberworm\CSS\Parser($input);
    $parser->parse();
});
