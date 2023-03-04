<?php

/** @var PhpFuzzer\Config $config */
$config->setTarget(function(string $input) {
    if (strlen($input) >= 4 && $input[0] == 'z' && $input[3] == 'k') {
        throw new Error('Bug!');
    }
});
