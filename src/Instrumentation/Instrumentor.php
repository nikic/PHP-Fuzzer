<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use PhpParser\PrettyPrinterAbstract;

final class Instrumentor {
    private Parser $parser;
    private NodeTraverser $traverser;
    private PrettyPrinterAbstract $prettyPrinter;

    public function __construct(string $runtimeContextName) {
        $this->parser = new Parser\Php7(new Lexer\Emulative());
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new Visitor(new Context($runtimeContextName)));
        $this->prettyPrinter = new PrettyPrinter\Standard();
    }

    public function instrument(string $code): string {
        $stmts = $this->parser->parse($code);
        $stmts = $this->traverser->traverse($stmts);
        return $this->prettyPrinter->prettyPrintFile($stmts);
    }
}