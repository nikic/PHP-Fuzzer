<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

final class Mutator {
    private RNG $rng;

    public function __construct(RNG $rng) {
        $this->rng = $rng;
    }

    public function mutate(string $str): string {
        $pos = $this->rng->randomPos($str);
        $str[$pos] = $this->rng->randomChar();
        return $str;
    }
}