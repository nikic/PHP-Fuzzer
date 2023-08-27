<?php declare(strict_types=1);

namespace PhpFuzzer;

final class Util {
    /**
     * Return the longest common directory prefix (including trailing slash) for the given paths.
     *
     * @param list<string> $strings
     */
    public static function getCommonPathPrefix(array $strings): string {
        if (empty($strings)) {
            return '';
        }

        $prefix = $strings[0];
        foreach ($strings as $string) {
            $prefixLen = \strspn($prefix ^ $string, "\0");
            $prefix = \substr($prefix, 0, $prefixLen);
        }

        if ($prefix === '') {
            return $prefix;
        }

        $len = \strlen($prefix);
        while ($prefix[$len-1] !== '/' && $prefix[$len-1] !== '\\') {
            --$len;
        }

        return \substr($prefix, 0, $len);
    }
}
