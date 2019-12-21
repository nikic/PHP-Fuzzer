<?php declare(strict_types=1);

namespace PhpFuzzer;

final class DictionaryParser {
    public function parse(string $code): array {
        $lines = explode("\n", $code);
        $dictionary = [];
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if (\strlen($line) === 0) {
                continue;
            }

            if ($line[0] === '#') {
                continue;
            }

            $regex = '/(?:\w+=)?"([^"\\\\]*(?:(?:\\\\(?:["\\\\]|x[0-9a-zA-Z]{2}))[^"\\\\]*)*)"/';
            if (!preg_match($regex, $line, $match)) {
                throw new \Exception('Line ' . ($idx+1) . ' of dictionary is invalid');
            }

            $escapedDictEntry = $match[1];
            $dictionary[] = preg_replace_callback('/\\\\(["\\\\]|x[0-9a-zA-Z]{2})/', function($match) {
                $escape = $match[1];
                if ($escape[0] === 'x') {
                    return chr(hexdec(substr($escape, 1)));
                }
                return $escape;
            }, $escapedDictEntry);
        }
        return $dictionary;
    }
}