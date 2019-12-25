PHP Fuzzer
==========

This library implements a [fuzzer](https://en.wikipedia.org/wiki/Fuzzing) for PHP,
which can be used to find bugs in libraries (particularly parsing libraries) by feeding
them "random" inputs. Feedback from edge coverage instrumentation is used to guide the
choice of "random" inputs, such that new code paths are visited. Many of the technical
details of this fuzzer are based on [libFuzzer](https://llvm.org/docs/LibFuzzer.html)
from the LLVM project.

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
be seeded based on existing unit tests. One input is provided per file. However, we can
also start from an empty corpus:

```shell script
mkdir corpus/
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

> Note: In order to fuzz libraries that have a dependency on PHP-Parser, it is necessary to use
> a prefixed phar. Run `box.phar compile` and then use `bin/php-fuzzer.phar`.