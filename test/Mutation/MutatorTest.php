<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

use PHPUnit\Framework\TestCase;

class MutatorTest extends TestCase {
    public function testMaxLen() {
        $rng = new RNG();
        $mutator = new Mutator($rng, new Dictionary());
        $tries = 1000;
        $mutators = $mutator->getMutators();
        foreach ($mutators as $mutator) {
            for ($i = 0; $i < $tries; $i++) {
                $maxLen = $rng->randomInt(100);
                $len = $rng->randomInt(100);
                $input = $rng->randomString($len);
                $result = $mutator($input, $maxLen);
                if ($result === null) {
                    continue;
                }
                $this->assertTrue(\strlen($result) <= $maxLen, "$mutator[1] violated maximum length constraint");
            }
        }
    }
}
