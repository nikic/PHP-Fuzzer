<?php declare(strict_types=1);

namespace PhpFuzzer\Mutation;

use PHPUnit\Framework\TestCase;

class MutatorTest extends TestCase {
    public function testMaxLen() {
        $mutator = new Mutator(new RNG(), new Dictionary());
        $maxLens = [0, 1, 10, PHP_INT_MAX];
        $inputs = ['', 'x', 'abc', str_repeat('x', 100)];
        $tries = 100;
        $mutators = $mutator->getMutators();
        foreach ($maxLens as $maxLen) {
            foreach ($inputs as $input) {
                foreach ($mutators as $mutator) {
                    for ($i = 0; $i < $tries; $i++) {
                        $result = $mutator($input, $maxLen);
                        if ($result === null) {
                            continue;
                        }
                        $this->assertTrue(\strlen($result) <= $maxLen, "$mutator[1]");
                    }
                }
            }
        }
    }
}