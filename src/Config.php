<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Mutation\Dictionary;

/**
 * Fuzzer configuration provided to fuzzing targets.
 * A call to setTarget() is required, all other options are optional.
 */
class Config {
    public \Closure $target;
    public Dictionary $dictionary;
    /** @var list<class-string<\Throwable>> */
    public array $allowedExceptions = [\Exception::class];
    public int $maxLen = PHP_INT_MAX;

    public function __construct() {
        $this->dictionary = new Dictionary();
    }

    /**
     * Set the fuzzing target.
     */
    public function setTarget(\Closure $target): void {
        $this->target = $target;
    }

    /**
     * Set which exceptions are not considered as fuzzing failures.
     * Defaults to just "Exception", considering all "Errors" failures.
     *
     * @param list<class-string<\Throwable>> $allowedExceptions
     */
    public function setAllowedExceptions(array $allowedExceptions): void {
        $this->allowedExceptions = $allowedExceptions;
    }

    public function setMaxLen(int $maxLen): void {
        $this->maxLen = $maxLen;
    }

    public function addDictionary(string $path): void {
        if (!is_file($path)) {
            throw new FuzzerException('Dictionary "' . $path . '" does not exist');
        }

        $parser = new DictionaryParser();
        $this->dictionary->addWords($parser->parse(file_get_contents($path)));
    }
}
