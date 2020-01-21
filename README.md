PHP Fuzzer
==========

This library implements a [fuzzer](https://en.wikipedia.org/wiki/Fuzzing) for PHP,
which can be used to find bugs in libraries (particularly parsing libraries) by feeding
them "random" inputs. Feedback from edge coverage instrumentation is used to guide the
choice of "random" inputs, such that new code paths are visited.

Installation
------------

**Phar (recommended)**: You can download a phar package of this library from the
[releases page](https://github.com/nikic/PHP-Fuzzer/releases). Using the phar is recommended,
because it avoids dependency conflicts with libraries using PHP-Parser.

**Composer**: `composer global require nikic/php-fuzzer`

Usage
-----

First, a definition of the target function is necessary. Here is an example target for
finding bugs in [microsoft/tolerant-php-parser](https://github.com/microsoft/tolerant-php-parser):

```php
<?php // target.php

/** @var PhpFuzzer\Fuzzer $fuzzer */

require 'path/to/tolerant-php-parser/vendor/autoload.php';

// Required: The target accepts a single input string and runs it through the tested
//           library. The target is allowed to throw normal Exceptions (which are ignored),
//           but Error exceptions are considered as a found bug.
$parser = new Microsoft\PhpParser\Parser();
$fuzzer->setTarget(function(string $input) use($parser) {
    $parser->parseSourceFile($input);
});

// Optional: Many targets don't exhibit bugs on large inputs that can't also be
//           produced with small inputs. Limiting the length may improve performance.
$fuzzer->setMaxLen(1024);
// Optional: A dictionary can be used to provide useful fragments to the fuzzer,
//           such as language keywords. This is particularly important if these
//           cannot be easily discovered by the fuzzer, because they are handled
//           by a non-instrumented PHP extension function such as token_get_all().
$fuzzer->addDictionary('example/php.dict');
```

The fuzzer is run against a corpus of initial "interesting" inputs, which can for example
be seeded based on existing unit tests. If no corpus is specified, a temporary corpus
directory will be created instead.

```shell script
# Run without initial corpus
php-fuzzer fuzz target.php
# Run with initial corpus (one input per file)
php-fuzzer fuzz target.php corpus/
```

If fuzzing is interrupted, it can later be resumed by specifying the same corpus directory.

Once a crash has been found, it is written into a `crash-HASH.txt` file. It is provided in the
form it was originally found, which may be unnecessarily complex and contain fragments not
relevant to the crash. As such, you likely want to reduce the crashing input first:

```shell script
php-fuzzer minimize-crash target.php crash-HASH.txt
```

This will product a sequence of successively smaller `minimized-HASH.txt` files. If you want to
quickly check the exception trace produced for a crashing input, you can use the `run-single`
command:

```shell script
php-fuzzer run-single target.php minimized-HASH.txt
```

Finally, it is possible to generate a HTML code coverage report, which shows which code blocks in
the target are hit when executing inputs from a given corpus:

```shell script
php-fuzzer report-coverage target.php corpus/ coverage_dir/
```

Additionally configuration options can be shown with `php-fuzzer --help`.

Bug types
---------

The fuzzer by default detects three kinds of bugs:

 * `Error` exceptions thrown by the fuzzing target. While `Exception` exceptions are considered a normal result for
   malformed input, uncaught `Error` exceptions always indicate programming error. They are most commonly produced by
   PHP itself, for example when calling a method on `null`.
 * Thrown notices and warnings (unless they are suppressed). The fuzzer registers an error handler that converts these
   to `Error` exceptions.
 * Timeouts. If the target runs longer than the specified timeout (default: 3s), it is assumed that the target has gone
   into an infinite loop. This is realized using `pcntl_alarm()` and an async signal handler that throws an `Error` on
   timeout.

Notably, none of these check whether the output of the target is correct, they only determine that the target does not
misbehave egregiously. One way to check output correctness is to compare two different implementations that are supposed
to produce identical results:

```php
$fuzzer->setTarget(function(string $input) use($parser1, $parser2) {
    $result1 = $parser1->parse($input);
    $result2 = $parser2->parse($input);
    if ($result1 != $result2) {
        throw new Error('Results do not match!');
    }
});
```

Technical
---------

Many of the technical details of this fuzzer are based on [libFuzzer](https://llvm.org/docs/LibFuzzer.html)
from the LLVM project. The following describes some of the implementation details.

### Instrumentation

To work efficiently, fuzzing requires feedback regarding the code-paths that were executed while testing a particular
fuzzing input. This coverage feedback is collected by "instrumenting" the fuzzing target. The
[include-interceptor](https://github.com/nikic/include-interceptor) library is used to transform the code of all
included files on the fly. The [PHP-Parser](https://github.com/nikic/PHP-Parser) library is used to parse the code and
find all the places where additional instrumentation code needs to be inserted.

Inside every basic block, the following code is inserted, where `BLOCK_INDEX` is a unique, per-block integer:

```php
$___key = (\PhpFuzzer\FuzzingContext::$prevBlock << 28) | BLOCK_INDEX;
\PhpFuzzer\FuzzingContext::$edges[$___key] = (\PhpFuzzer\FuzzingContext::$edges[$___key] ?? 0) + 1;
\PhpFuzzer\FuzzingContext::$prevBlock = BLOCK_INDEX;
```

This assumes that the block index is at most 28-bit large and counts the number of `(prev_block, cur_block)` pairs
that are observed during execution. The generated code is unfortunately fairly expensive, due to the need to deal with
uninitialized edge counts, and the use of static properties. In the future, it would be possible to create a PHP
extension that can collect the coverage feedback much more efficiently.

In some cases, basic blocks are part of expressions, in which case we cannot easily insert additional code. In these
cases we instead insert a call to a method that contains the above code:

```php
if ($foo && $bar) { ... }
// becomes
if ($foo && \PhpFuzzer\FuzzingContext::traceBlock(BLOCK_INDEX, $bar)) { ... }
```

In the future, it would be beneficial to also instrument comparisons, such that we can automatically determine
dictionary entries from comparisons like `$foo == "SOME_STRING"`.

### Features

Fuzzing inputs are considered "interesting" if they contain new features that have not been observed with other inputs
that are already part of the corpus. This library uses course-grained edge hit counts as features:

    ft = (approx_hits << 56) | (prev_block << 28) | cur_block

The approximate hit count reduces the actual hit count to 8 categories (based on AFL):

    0: 0 hits
    1: 1 hit
    2: 2 hits
    3: 3 hits
    4: 4-7 hits
    5: 8-15 hits
    6: 16-127 hits
    7: >=128 hits

As such, each input is associated with a set of integers representing features. Additionally, it has a set of "unique
features", which are features not seen in any other corpus inputs at the time the input was tested.

If an input has unique features, then it is added to the corpus (NEW). If an input B was created by mutating an input A,
but input B is shorter and has all the unique features of input A, then A is replaced by B in the corpus (REDUCE).

### Mutation

On each iteration, a random input from the current corpus is chosen, and then mutated using a sequence of mutators. The
following mutators (taken from libFuzzer) are currently implemented:

 * `EraseBytes`: Remove a number of bytes.
 * `InsertByte`: Insert a new random byte.
 * `InsertRepeatedBytes`: Insert a random byte repeated multiple times.
 * `ChangeByte`: Replace a byte with a random byte.
 * `ChangeBit`: Flip a single bit.
 * `ShuffleBytes`: Shuffle a small substring.
 * `ChangeASCIIInt`: Change an ASCII integer by incrementing/decrementing/doubling/halving.
 * `ChangeBinInt`: Change a binary integer by adding a small random amount.
 * `CopyPart`: Copy part of the string into another part, either by overwriting or inserting.
 * `CrossOver`: Cross over with another corpus entry with multiple strategies.
 * `AddWordFromManualDictionary`: Insert or overwrite with a word from the dictionary (if any).

Mutation is subject to a maximum length constrained. While an overall maximum length can be specified by the target
(`setMaxLength()`), the fuzzer also performs automatic length control (`--len-control-factor`). The maximum length
is initially set to a very low value and then increased by `log(maxlen)` whenever no action (NEW or REDUCE) has been
taken for the last `len_control_factor * log(maxlen)` runs.

The higher the length control factor, the more aggressively the fuzzer will explore short inputs before allowing longer
inputs. This significantly reduces the size of the generated corpus, but makes initial exploration slower.

Findings
--------

 * [tolerant-php-parser](https://github.com/microsoft/tolerant-php-parser):
   [#305](https://github.com/microsoft/tolerant-php-parser/issues/305)
 * [PHP-CSS-Parser](https://github.com/sabberworm/PHP-CSS-Parser):
   [#181](https://github.com/sabberworm/PHP-CSS-Parser/issues/181)
   [#182](https://github.com/sabberworm/PHP-CSS-Parser/issues/182)
   [#183](https://github.com/sabberworm/PHP-CSS-Parser/issues/183)
   [#184](https://github.com/sabberworm/PHP-CSS-Parser/issues/184)
 * [league/uri](https://github.com/thephpleague/uri):
   [#150](https://github.com/thephpleague/uri/issues/150)
 * [amphp/http-client](https://github.com/amphp/http-client)
   [#236](https://github.com/amphp/http-client/issues/236)
 * [amphp/hpack](https://github.com/amphp/hpack)
   [#8](https://github.com/amphp/hpack/issues/8)
