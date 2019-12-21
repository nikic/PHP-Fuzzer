<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

final class Mutator {
    private RNG $rng;
    private array $mutators;

    public function __construct(RNG $rng) {
        $this->rng = $rng;
        $this->mutators = [
            [$this, 'mutateEraseBytes'],
            [$this, 'mutateInsertByte'],
            [$this, 'mutateInsertRepeatedBytes'],
            [$this, 'mutateChangeByte'],
            [$this, 'mutateChangeBit'],
        ];
    }

    private function mutateEraseBytes(string $str): ?string {
        $len = \strlen($str);
        if ($len <= 1) {
            return null;
        }
        $numBytes = $this->rng->randomInt($len >> 1) + 1;
        $pos = $this->rng->randomInt($len - $numBytes + 1);
        return \substr($str, 0, $pos)
            . \substr($str, $pos + $numBytes);
    }

    private function mutateInsertByte(string $str): string {
        $pos = $this->rng->randomPosOrEnd($str);
        return \substr($str, 0, $pos)
            . $this->rng->randomChar()
            . \substr($str, $pos);
    }

    private function mutateInsertRepeatedBytes(string $str): string {
        // TODO
        return $str;
    }

    private function mutateChangeByte(string $str): ?string {
        if ($str === '') {
            return null;
        }
        $pos = $this->rng->randomPos($str);
        $str[$pos] = $this->rng->randomChar();
        return $str;
    }

    private function mutateChangeBit(string $str): ?string {
        if ($str === '') {
            return null;
        }
        $pos = $this->rng->randomPos($str);
        $bit = 1 << $this->rng->randomInt(8);
        $str[$pos] = \chr(\ord($str[$pos]) ^ $bit);
        return $str;
    }

    public function mutate(string $str): string {
        while (true) {
            $mutator = $this->rng->randomElement($this->mutators);
            $newStr = $mutator($str);
            if (null !== $newStr) {
                return $newStr;
            }
        }
    }
}