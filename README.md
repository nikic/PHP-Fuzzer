PHP Fuzzer
==========

This experiment implements a primitive fuzzer for PHP. The fuzzing target is instrumented via include interception in
order to record edge coverage during execution of the target. Fuzzer inputs are mutated in an attempt to increase
edge coverage. A reduced representation of the coverage can also be rendered.

This is just a quick experiment, it does not work particularly well.

Usage
-----

```shell script
php-fuzz --target example/target_simple.php example/corpus

# Run with starting corpus
php-fuzz --target example/target_tolerant_php_parser.php example/corpus
# Minimize a crash
php-fuzz --target example/target_tolerant_php_parser.php --minimize-crash crashing_input.txt
# Run single input (e.g. to check for crash)
php-fuzz --target example/target_tolerant_php_parser.php single_input.txt
```