<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Instrumentation\FileInfo;

final class CoverageRenderer {
    private string $outDir;

    public function __construct(string $outDir) {
        $this->outDir = $outDir;
    }

    /** @param FileInfo[] $fileInfos */
    public function render(array $fileInfos, array $seenBlocks): void {
        @mkdir($this->outDir);

        $prefix = $this->getCommonPrefix(array_keys($fileInfos));
        foreach ($fileInfos as $path => $fileInfo) {
            $posToBlockIndex = array_flip($fileInfo->blockIndexToPos);
            ksort($posToBlockIndex);

            $code = file_get_contents($path);
            $result = '<pre>';
            $lastPos = 0;
            foreach ($posToBlockIndex as $pos => $blockIndex) {
                $result .= htmlspecialchars(\substr($code, $lastPos, $pos - $lastPos));
                $covered = isset($seenBlocks[$blockIndex]);
                $color = $covered ? "green" : "red";
                $result .= '<span style="background-color: ' . $color . '">' . $code[$pos] . '</span>';
                $lastPos = $pos + 1;
            }
            $result .= htmlspecialchars(\substr($code, $lastPos));
            $result .= '</pre>';

            $shortPath = str_replace($prefix, '', $path);
            $outPath = $this->outDir . '/' . $shortPath . '.html';
            @mkdir(dirname($outPath), 0777, true);
            file_put_contents($outPath, $result);
        }
    }

    private function getCommonPrefix(array $strings): string {
        if (empty($strings)) {
            return '';
        }

        $prefix = $strings[0];
        foreach ($strings as $string) {
            $prefixLen = \strspn($prefix ^ $string, "\0");
            $prefix = \substr($prefix, 0, $prefixLen);
        }
        return $prefix;
    }
}