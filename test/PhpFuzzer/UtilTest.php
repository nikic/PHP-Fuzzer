<?php declare(strict_types=1);

namespace PhpFuzzer;

use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase {
    /**
     * @dataProvider provideTestGetCommonPathPrefix
     */
    public function testGetCommonPathPrefix(array $paths, string $expectedPrefix) {
        $this->assertSame($expectedPrefix, Util::getCommonPathPrefix($paths));
    }

    public function provideTestGetCommonPathPrefix(): array {
        return [
            [[], ''],
            [['/foo/bar/baz.php'], '/foo/bar/'],
            [['C:\foo\bar\baz.php'], 'C:\foo\bar\\'],
            [['bar', 'foo'], ''],
            [['/foo/bar/abc.php', '/foo/bar/abd.php'], '/foo/bar/'],
            [['C:\foo\bar\abc.php', 'C:\foo\bar\abd.php'], 'C:\foo\bar\\'],
            [['/foo/abc/bar.php', '/foo/abd/bar.php'], '/foo/'],
            [['C:\foo\abc\bar.php', 'C:\foo\abd\bar.php'], 'C:\foo\\'],
        ];
    }
}
