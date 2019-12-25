PHP Fuzzer
==========

This experiment implements a primitive fuzzer for PHP. The fuzzing target is instrumented via include interception in
order to record edge coverage during execution of the target. Fuzzer inputs are mutated in an attempt to increase
edge coverage.

Usage
-----

First, a definition of the target function is necessary. Here is a basic example based on
`example/target_tolerant_php_parser.php`:

```php
<?php // target.php

/** @var PhpFuzzer\Fuzzer $fuzzer */

// Optional: Many targets don't exhibit bugs on large inputs that can't also be
//           produced with small inputs. Limiting the length may improve performance.
$fuzzer->setMaxLen(1024);
// Optional: A dictionary can be used to provide useful fragments to the fuzzer,
//           such as language keywords. This is particularly important if these
//           cannot be easily discovered by the fuzzer, because they are handled
//           by a non-instrumented PHP extension function such as token_get_all().
$fuzzer->addDictionary('example/php.dict');

require 'path/to/tolerant-php-parser/vendor/autoload.php';

$parser = new Microsoft\PhpParser\Parser();
$fuzzer->setTarget(function(string $input) use($parser) {
    $parser->parseSourceFile($input);
});
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