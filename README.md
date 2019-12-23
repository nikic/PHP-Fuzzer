PHP Fuzzer
==========

This experiment implements a primitive fuzzer for PHP. The fuzzing target is instrumented via include interception in
order to record edge coverage during execution of the target. Fuzzer inputs are mutated in an attempt to increase
edge coverage. A reduced representation of the coverage can also be rendered.

This is just a quick experiment, it does not work particularly well.

Usage
-----

First, a definition of the target function is necessary. Here is a basic example from `example/target_simple.php`:

```php
<?php // target.php

/** @var PhpFuzzer\Fuzzer $fuzzer */
$fuzzer->setTarget(function(string $input) {
    if (strlen($input) >= 4 && $input[0] == 'z' && $input[3] == 'k') {
        throw new Error('Bug!');
    }
});
```

See `example/target_tolerant_php_parser.php` for a more realistic target.

Then, one of multiple commands may be used through the `php-fuzz` binary:

```shell script
# Run the fuzzer!
# corpus/ specifies both the starting corpus,
# as well as the directory for new corpus entries
php-fuzzer fuzz target.php corpus/

# After a crashing input has been found, you may want to minimize it
php-fuzzer minimize-crash target.php crashing_input.txt

# You can also simply run a single intput through the target
php-fuzzer run-single single_input.txt

# To see which code-paths have been explored, an HTML coverage report can be generated
php-fuzzer report-coverage corpus/ coverage_dir/
```