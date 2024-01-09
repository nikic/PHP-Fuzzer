<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

final class Instrumentor {
    private Parser $parser;
    private NodeTraverser $traverser;
    private Context $context;

    public function __construct(string $runtimeContextName, PhpVersion $phpVersion) {
        $this->parser = (new ParserFactory())->createForVersion($phpVersion);
        $this->traverser = new NodeTraverser();
        $this->context = new Context($runtimeContextName);
        $this->traverser->addVisitor(new Visitor($this->context));
    }

    public function instrument(string $code, FileInfo $fileInfo): string {
        $mutableStr = new MutableString($code);
        $this->context->fileInfo = $fileInfo;
        $this->context->code = $mutableStr;
        $stmts = $this->parser->parse($code);
        $this->traverser->traverse($stmts);
        return $mutableStr->getModifiedString();
    }
}
