<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

/** String that can be modified without invalidating offsets into it */
final class MutableString {
    private string $string;
    // [[pos, len, newString, order]]
    private array $modifications = [];

    public function __construct(string $string) {
        $this->string = $string;
    }

    public function replace(int $pos, int $len, string $newString, int $order = 0): void {
        $this->modifications[] = [$pos, $len, $newString, $order];
    }

    public function insert(int $pos, string $newString, int $order = 0): void {
        $this->replace($pos, 0, $newString, $order);
    }

    public function getOrigString(): string {
        return $this->string;
    }

    public function getModifiedString(): string {
        // Sort by position
        usort($this->modifications, function($a, $b) {
            return ($a[0] <=> $b[0]) ?: ($a[3] <=> $b[3]);
        });

        $result = '';
        $startPos = 0;
        foreach ($this->modifications as list($pos, $len, $newString)) {
            $result .= substr($this->string, $startPos, $pos - $startPos);
            $result .= $newString;
            $startPos = $pos + $len;
        }
        $result .= substr($this->string, $startPos);
        return $result;
    }
}

