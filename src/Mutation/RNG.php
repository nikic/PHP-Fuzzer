<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

final class RNG {
    public function randomInt(int $maxExclusive): int {
        return \mt_rand(0, $maxExclusive - 1);
    }

    public function randomIntRange(int $minInclusive, $maxInclusive): int {
        return \mt_rand($minInclusive, $maxInclusive);
    }

    public function randomChar(): string {
        // TODO: Biasing?
        return \chr($this->randomInt(256));
    }

    public function randomPos(string $str): int {
        $len = \strlen($str);
        if ($len === 0) {
            throw new \Error("String must not be empty!");
        }
        return $this->randomInt($len);
    }

    public function randomPosOrEnd(string $str): int {
        return $this->randomInt(\strlen($str) + 1);
    }

    public function randomElement(array $array) {
        return $array[$this->randomInt(\count($array))];
    }

    public function randomBool(): bool {
        return (bool) \mt_rand(0, 1);
    }

    public function randomString(int $len): string {
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $result .= $this->randomChar();
        }
        return $result;
    }
}