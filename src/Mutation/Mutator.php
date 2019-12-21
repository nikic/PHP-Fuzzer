<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

/*
 * Mutations based on https://github.com/llvm/llvm-project/blob/master/compiler-rt/lib/fuzzer/FuzzerMutate.cpp.
 */
final class Mutator {
    private RNG $rng;
    private array $mutators;
    private ?string $crossOverWith;

    public function __construct(RNG $rng) {
        $this->rng = $rng;
        $this->mutators = [
            [$this, 'mutateEraseBytes'],
            [$this, 'mutateInsertByte'],
            [$this, 'mutateInsertRepeatedBytes'],
            [$this, 'mutateChangeByte'],
            [$this, 'mutateChangeBit'],
            [$this, 'mutateCopyPart'],
            [$this, 'mutateCrossOver'],
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
        $len = \strlen($str);
        $numBytes = $this->rng->randomIntRange(3, 128);
        $pos = $this->rng->randomPosOrEnd($str);
        // TODO: Biasing?
        $char = $this->rng->randomChar();
        return \substr($str, 0, $pos)
            . str_repeat($char, $numBytes)
            . \substr($str, $pos);
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

    private function copyPartOf(string $from, string $to): string {
        $toLen = \strlen($to);
        $fromLen = \strlen($from);
        $toBeg = $this->rng->randomPos($to);
        $numBytes = $this->rng->randomInt($toLen - $toBeg) + 1;
        $numBytes = \min($numBytes, $fromLen);
        $fromBeg = $this->rng->randomInt($fromLen - $numBytes + 1);
        return \substr($to, 0, $toBeg)
            . \substr($from, $fromBeg, $numBytes)
            . \substr($to, $toBeg + $numBytes);
    }

    private function insertPartOf(string $from, string $to): string {
        $toLen = \strlen($to);
        $fromLen = \strlen($from);
        $numBytes = $this->rng->randomInt($fromLen) + 1;
        $fromBeg = $this->rng->randomInt($fromLen - $numBytes + 1);
        $toInsertPos = $this->rng->randomPosOrEnd($to);
        return \substr($to, 0, $toInsertPos)
            . \substr($from, $fromBeg, $numBytes)
            . \substr($to, $toInsertPos);
    }

    private function mutateCopyPart(string $str): ?string {
        if (empty($str)) {
            return null;
        }
        if ($this->rng->randomBool()) {
            return $this->copyPartOf($str, $str);
        } else {
            return $this->insertPartOf($str, $str);
        }
    }

    private function mutateCrossOver(string $str): ?string {
        if ($this->crossOverWith === null) {
            return null;
        }
        if (\strlen($str) === 0 || \strlen($this->crossOverWith) === 0) {
            return null;
        }
        switch ($this->rng->randomInt(3)) {
            case 0:
                // TODO: CrossOver
                return null;
            case 1:
                return $this->insertPartOf($this->crossOverWith, $str);
            case 2:
                return $this->copyPartOf($this->crossOverWith, $str);
            default:
                assert(false);
        }
    }

    public function mutate(string $str, ?string $crossOverWith): string {
        $this->crossOverWith = $crossOverWith;
        while (true) {
            $mutator = $this->rng->randomElement($this->mutators);
            $newStr = $mutator($str);
            if (null !== $newStr) {
                return $newStr;
            }
        }
    }
}