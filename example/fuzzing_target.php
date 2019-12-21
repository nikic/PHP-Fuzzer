<?php declare(strict_types=1);

function fuzzingTarget1(string $input) {
    if (strlen($input) > 0 && $input[0] == 'z') {
        throw new Error('Bug!');
    }
}