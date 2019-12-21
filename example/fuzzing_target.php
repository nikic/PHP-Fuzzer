<?php declare(strict_types=1);

function fuzzingTarget1(string $input) {
    /*if (strlen($input) >= 4) {
        if ($input[0] == 'z') {
            if ($input[3] == 'k') {
                throw new Error('Bug!');
            }
        }
    }*/

    if (strlen($input) >= 4 && $input[0] == 'z' && $input[3] == 'k') {
        throw new Error('Bug!');
    }
}