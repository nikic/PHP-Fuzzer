<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

final class Visitor extends NodeVisitorAbstract {
    private Context $context;

    public function __construct(Context $context) {
        $this->context = $context;
    }

    public function leaveNode(Node $node) {
        // In these cases it is sufficient to insert a stub at the start.
        if ($node instanceof Node\FunctionLike ||
            $node instanceof Stmt\Case_ ||
            $node instanceof Stmt\Catch_ ||
            $node instanceof Stmt\Else_ ||
            $node instanceof Stmt\ElseIf_ ||
            $node instanceof Stmt\Finally_ ||
            $node instanceof Stmt\TryCatch ||
            $node instanceof Stmt\While_
        ) {
            $this->insertInlineBlockStub($node->stmts);
            return null;
        }

        // In these cases we should additionally insert one after the node.
        if ($node instanceof Stmt\Do_ ||
            $node instanceof Stmt\If_ ||
            $node instanceof Stmt\For_ ||
            $node instanceof Stmt\Foreach_
        ) {
            $this->insertInlineBlockStub($node->stmts);
            return [$node, $this->generateInlineBlockStub()];
        }

        // In these cases we need to insert one after the node only.
        if ($node instanceof Stmt\Label ||
            $node instanceof Stmt\Switch_
        ) {
            return [$node, $this->generateInlineBlockStub()];
        }

        return null;
    }

    private function insertInlineBlockStub(array &$stmts): void {
        // Insert inline block stub at start of statements.
        array_splice($stmts, 0, 0, $this->generateInlineBlockStub());
    }

    private function generateInlineBlockStub(): array {
        // We generate the following code:
        // ++InstrumentationContext::$edges[
        //     (InstrumentationContext::$prevBlock << 32) |
        //     BLOCK_INDEX
        // ];
        // InstrumentationContext::$prevBlock = BLOCK_INDEX;
        $blockIndex = new Scalar\LNumber($this->context->getNewBlockIndex());
        $instrumentationContext = new Node\Name\FullyQualified('InstrumentationContext');
        $edgesVar = new Expr\StaticPropertyFetch($instrumentationContext, 'edges');
        $prevBlockVar = new Expr\StaticPropertyFetch($instrumentationContext, 'prevBlock');
        return [
            new Stmt\Expression(
                new Expr\PreInc(new Expr\ArrayDimFetch(
                    $edgesVar,
                    new Expr\BinaryOp\BitwiseOr(
                        new Expr\BinaryOp\ShiftLeft(
                            $prevBlockVar,
                            new Scalar\LNumber(32)
                        ),
                        $blockIndex
                    )
                ))
            ),
            new Stmt\Expression(
                new Expr\Assign($prevBlockVar, $blockIndex)
            ),
        ];
    }
}