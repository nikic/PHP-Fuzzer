<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

final class RNG {
    public function randomInt(int $min, int $max): int {
        return \mt_rand($min, $max);
    }

    public function randomChar(): string {
        return \chr($this->randomInt(0, 255));
    }

    public function randomPos(string $str) {
        return $this->randomInt(0, \strlen($str) - 1);
    }
}