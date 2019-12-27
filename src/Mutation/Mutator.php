<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

/*
 * Mutations based on https://github.com/llvm/llvm-project/blob/master/compiler-rt/lib/fuzzer/FuzzerMutate.cpp.
 */
final class Mutator {
    private RNG $rng;
    private Dictionary $dictionary;
    private array $mutators;
    private ?string $crossOverWith = null; // TODO: Get rid of this

    public function __construct(RNG $rng, Dictionary $dictionary) {
        $this->rng = $rng;
        $this->dictionary = $dictionary;
        $this->mutators = [
            [$this, 'mutateEraseBytes'],
            [$this, 'mutateInsertByte'],
            [$this, 'mutateInsertRepeatedBytes'],
            [$this, 'mutateChangeByte'],
            [$this, 'mutateChangeBit'],
            [$this, 'mutateShuffleBytes'],
            [$this, 'mutateChangeASCIIInt'],
            [$this, 'mutateChangeBinInt'],
            [$this, 'mutateCopyPart'],
            [$this, 'mutateCrossOver'],
            [$this, 'mutateAddWordFromManualDictionary'],
        ];
    }

    public function getMutators(): array {
        return $this->mutators;
    }

    private function randomBiasedChar(): string {
        if ($this->rng->randomBool()) {
            return $this->rng->randomChar();
        }
        $chars = "!*'();:@&=+$,/?%#[]012Az-`~.\xff\x00";
        return $chars[$this->rng->randomPos($chars)];
    }

    public function mutateEraseBytes(string $str, int $maxLen): ?string {
        $len = \strlen($str);
        if ($len <= 1) {
            return null;
        }

        $minNumBytes = $maxLen < $len ? $len - $maxLen : 0;
        $maxNumBytes = min($minNumBytes + ($len >> 1), $len);
        $numBytes = $this->rng->randomIntRange($minNumBytes, $maxNumBytes);
        $pos = $this->rng->randomInt($len - $numBytes + 1);
        return \substr($str, 0, $pos)
            . \substr($str, $pos + $numBytes);
    }

    public function mutateInsertByte(string $str, int $maxLen): ?string {
        if (\strlen($str) >= $maxLen) {
            return null;
        }

        $pos = $this->rng->randomPosOrEnd($str);
        return \substr($str, 0, $pos)
            . $this->randomBiasedChar()
            . \substr($str, $pos);
    }

    public function mutateInsertRepeatedBytes(string $str, int $maxLen): ?string {
        $minNumBytes = 3;
        $len = \strlen($str);
        if ($len + $minNumBytes >= $maxLen) {
            return null;
        }

        $maxNumBytes = min($maxLen - $len, 128);
        $numBytes = $this->rng->randomIntRange($minNumBytes, $maxNumBytes);
        $pos = $this->rng->randomPosOrEnd($str);
        // TODO: Biasing?
        $char = $this->rng->randomChar();
        return \substr($str, 0, $pos)
            . str_repeat($char, $numBytes)
            . \substr($str, $pos);
    }

    public function mutateChangeByte(string $str, int $maxLen): ?string {
        if ($str === '' || \strlen($str) > $maxLen) {
            return null;
        }

        $pos = $this->rng->randomPos($str);
        $str[$pos] = $this->randomBiasedChar();
        return $str;
    }

    public function mutateChangeBit(string $str, int $maxLen): ?string {
        if ($str === '' || \strlen($str) > $maxLen) {
            return null;
        }

        $pos = $this->rng->randomPos($str);
        $bit = 1 << $this->rng->randomInt(8);
        $str[$pos] = \chr(\ord($str[$pos]) ^ $bit);
        return $str;
    }

    public function mutateShuffleBytes(string $str, int $maxLen): ?string {
        $len = \strlen($str);
        if ($str === '' || $len > $maxLen) {
            return null;
        }
        $numBytes = $this->rng->randomInt(min($len, 8)) + 1;
        $pos = $this->rng->randomInt($len - $numBytes + 1);
        // TODO: This does not use the RNG!
        return \substr($str, 0, $pos)
            . \str_shuffle(\substr($str, $pos, $numBytes))
            . \substr($str, $pos + $numBytes);

    }

    public function mutateChangeASCIIInt(string $str, int $maxLen): ?string {
        $len = \strlen($str);
        if ($str === '' || $len > $maxLen) {
            return null;
        }

        $beginPos = $this->rng->randomPos($str);
        while ($beginPos < $len && !\ctype_digit($str[$beginPos])) {
            $beginPos++;
        }
        if ($beginPos === $len) {
            return null;
        }
        $endPos = $beginPos;
        while ($endPos < $len && \ctype_digit($str[$endPos])) {
            $endPos++;
        }
        // TODO: We won't be able to get large unsigned integers here.
        $int = (int) \substr($str, $beginPos, $endPos - $beginPos);
        switch ($this->rng->randomInt(4)) {
            case 0:
                $int++;
                break;
            case 1:
                $int--;
                break;
            case 2:
                $int >>= 1;
                break;
            case 3:
                $int <<= 1;
                break;
            default:
                assert(false);
        }

        $intStr = (string) $int;
        if ($len - ($endPos - $beginPos) + \strlen($intStr) > $maxLen) {
            return null;
        }

        return \substr($str, 0, $beginPos)
            . $intStr
            . \substr($str, $endPos);
    }

    public function mutateChangeBinInt(string $str, int $maxLen): ?string {
        $len = \strlen($str);
        if ($len > $maxLen) {
            return null;
        }

        $packCodes = [
            'C' => 1,
            'n' => 2, 'v' => 2,
            'N' => 4, 'V' => 4,
            'J' => 8, 'P' => 8,
        ];
        $packCode = $this->rng->randomElement(array_keys($packCodes));
        $numBytes = $packCodes[$packCode];
        if ($numBytes > $len) {
            return null;
        }

        $pos = $this->rng->randomInt($len - $numBytes + 1);
        if ($pos < 64 && $this->rng->randomInt(4) == 0) {
            $int = $len;
        } else {
            $int = \unpack($packCode, $str, $pos)[1];
            $add = $this->rng->randomIntRange(-10, 10);
            $int += $add;
            if ($add == 0 && $this->rng->randomBool()) {
                $int = -$int;
            }
        }
        return \substr($str, 0, $pos)
             . \pack($packCode, $int)
             . \substr($str, $pos + $numBytes);
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

    private function insertPartOf(string $from, string $to, int $maxLen): ?string {
        $toLen = \strlen($to);
        if ($toLen >= $maxLen) {
            return null;
        }

        $fromLen = \strlen($from);
        $maxNumBytes = min($maxLen - $toLen, $fromLen);
        $numBytes = $this->rng->randomInt($maxNumBytes) + 1;
        $fromBeg = $this->rng->randomInt($fromLen - $numBytes + 1);
        $toInsertPos = $this->rng->randomPosOrEnd($to);
        return \substr($to, 0, $toInsertPos)
            . \substr($from, $fromBeg, $numBytes)
            . \substr($to, $toInsertPos);
    }

    private function crossOver(string $str1, string $str2, int $maxLen): string {
        $maxLen = $this->rng->randomInt($maxLen) + 1;
        $len1 = \strlen($str1);
        $len2 = \strlen($str2);
        $pos1 = 0;
        $pos2 = 0;
        $result = '';
        $usingStr1 = true;
        while (\strlen($result) < $maxLen && ($pos1 < $len1 || $pos2 < $len2)) {
            $maxLenLeft = $maxLen - \strlen($result);
            if ($usingStr1) {
                if ($pos1 < $len1) {
                    $maxExtraLen = min($len1 - $pos1, $maxLenLeft);
                    $extraLen = $this->rng->randomInt($maxExtraLen) + 1;
                    $result .= \substr($str1, $pos1, $extraLen);
                    $pos1 += $extraLen;
                }
            } else {
                if ($pos2 < $len2) {
                    $maxExtraLen = min($len2 - $pos2, $maxLenLeft);
                    $extraLen = $this->rng->randomInt($maxExtraLen) + 1;
                    $result .= \substr($str2, $pos2, $extraLen);
                    $pos2 += $extraLen;
                }
            }
            $usingStr1 = !$usingStr1;
        }
        return $result;
    }

    public function mutateCopyPart(string $str, int $maxLen): ?string {
        $len = \strlen($str);
        if ($str === '' || $len > $maxLen) {
            return null;
        }
        if ($len == $maxLen || $this->rng->randomBool()) {
            return $this->copyPartOf($str, $str);
        } else {
            return $this->insertPartOf($str, $str, $maxLen);
        }
    }

    public function mutateCrossOver(string $str, int $maxLen): ?string {
        if ($this->crossOverWith === null) {
            return null;
        }
        $len = \strlen($str);
        if ($len > $maxLen || $len === 0 || \strlen($this->crossOverWith) === 0) {
            return null;
        }
        switch ($this->rng->randomInt(3)) {
            case 0:
                return $this->crossOver($str, $this->crossOverWith, $maxLen);
            case 1:
                if ($len == $maxLen) {
                    return $this->insertPartOf($this->crossOverWith, $str, $maxLen);
                }
                /* fallthrough */
            case 2:
                return $this->copyPartOf($this->crossOverWith, $str);
            default:
                assert(false);
        }
    }

    public function mutateAddWordFromManualDictionary(string $str, int $maxLen): ?string {
        $len = \strlen($str);
        if ($len > $maxLen) {
            return null;
        }
        if ($this->dictionary->isEmpty()) {
            return null;
        }

        $word = $this->rng->randomElement($this->dictionary->dict);
        $wordLen = \strlen($word);
        if ($this->rng->randomBool()) {
            // Insert word.
            if ($len + $wordLen > $maxLen) {
                return null;
            }

            $pos = $this->rng->randomPosOrEnd($str);
            return \substr($str, 0, $pos)
                . $word
                . \substr($str, $pos);
        } else {
            // Overwrite with word.
            if ($wordLen > $len) {
                return null;
            }

            $pos = $this->rng->randomInt($len - $wordLen + 1);
            return \substr($str, 0, $pos)
                . $word
                . \substr($str, $pos + $wordLen);
        }
    }

    public function mutate(string $str, int $maxLen, ?string $crossOverWith): string {
        $this->crossOverWith = $crossOverWith;
        while (true) {
            $mutator = $this->rng->randomElement($this->mutators);
            $newStr = $mutator($str, $maxLen);
            if (null !== $newStr) {
                assert(\strlen($newStr) <= $maxLen, 'Mutator ' . $mutator[1]);
                return $newStr;
            }
        }
    }
}