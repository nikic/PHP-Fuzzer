PHP Fuzzer
==========

This experiment implements a primitive fuzzer for PHP. The fuzzing target is instrumented via include interception in
order to record edge coverage during execution of the target. Fuzzer inputs are mutated in an attempt to increase
edge coverage. A reduced representation of the coverage can also be rendered.

Basic usage shown in `example/fuzz_*.php`.

This is just a quick experiment, it does not work particularly well.