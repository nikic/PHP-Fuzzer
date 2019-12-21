<?php declare(strict_types=1);

namespace PhpFuzzer;

use PHPUnit\Framework\TestCase;

class DictionaryParserTest extends TestCase {
    public function testParse() {
        $code = <<<'CODE'
# Comment
foo="bar"
"baz"
"\\abc\\abc\\"
"\"abc\"abc\""
"\x00abc\x00abc\x00"
CODE;
        $expected = [
            "bar",
            "baz",
            "\\abc\\abc\\",
            "\"abc\"abc\"",
            "\x00abc\x00abc\x00"
        ];

        $dictionaryParser = new DictionaryParser();
        $this->assertSame($expected, $dictionaryParser->parse($code));
    }
}
