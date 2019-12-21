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
            $this->prependInlineBlockStub($node->stmts);
            return null;
        }

        // In these cases we should additionally insert one after the node.
        if ($node instanceof Stmt\Do_ ||
            $node instanceof Stmt\If_ ||
            $node instanceof Stmt\For_ ||
            $node instanceof Stmt\Foreach_
        ) {
            $this->prependInlineBlockStub($node->stmts);
            return [$node, ...$this->generateInlineBlockStub()];
        }

        // In these cases we need to insert one after the node only.
        if ($node instanceof Stmt\Label ||
            $node instanceof Stmt\Switch_
        ) {
            return [$node, ...$this->generateInlineBlockStub()];
        }

        // TODO: BinaryOp\And, BinaryOp\Or
        // TODO: Ternary, BinaryOp\Coalesce, AssignOp\Coalesce
        // TODO: Yield, YieldFrom
        // TODO: Comparison instrumentation
        return null;
    }

    private function prependInlineBlockStub(array &$stmts): void {
        // Insert inline block stub at start of statements.
        array_splice($stmts, 0, 0, $this->generateInlineBlockStub());
    }

    private function generateInlineBlockStub(): array {
        // We generate the following code:
        // $___key = (Context::$prevBlock << 32) | BLOCK_INDEX;
        // Context::$edges[$___key] = (Context::$edges[$___key] ?? 0) + 1;
        // Context::$prevBlock = BLOCK_INDEX;
        // TODO: When I originally picked this format, I forgot about the initialization issue.
        // TODO: It probably makes sense to switch this to something that can be pre-initialized.
        $blockIndex = new Scalar\LNumber($this->context->getNewBlockIndex());
        $keyVar = new Expr\Variable('___key');
        $instrumentationContext = new Node\Name\FullyQualified($this->context->runtimeContextName);
        $edgesVar = new Expr\StaticPropertyFetch($instrumentationContext, 'edges');
        $edgesKeyVar = new Expr\ArrayDimFetch($edgesVar, $keyVar);
        $prevBlockVar = new Expr\StaticPropertyFetch($instrumentationContext, 'prevBlock');
        return [
            new Stmt\Expression(
                new Expr\Assign($keyVar, new Expr\BinaryOp\BitwiseOr(
                    new Expr\BinaryOp\ShiftLeft(
                        $prevBlockVar,
                        new Scalar\LNumber(32)
                    ),
                    $blockIndex
                ))
            ),
            new Stmt\Expression(
                new Expr\Assign(
                    $edgesKeyVar,
                    new Expr\BinaryOp\Plus(
                        new Expr\BinaryOp\Coalesce(
                            $edgesKeyVar,
                            new Scalar\LNumber(0)
                        ),
                        new Scalar\LNumber(1)
                    )
                )
            ),
            new Stmt\Expression(
                new Expr\Assign($prevBlockVar, $blockIndex)
            ),
        ];
    }
}