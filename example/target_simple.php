<?php

/** @var PhpFuzzer\Fuzzer $fuzzer */
$fuzzer->setTarget(function(string $input) {
    if (strlen($input) >= 4 && $input[0] == 'z' && $input[3] == 'k') {
        throw new Error('Bug!');
    }
});