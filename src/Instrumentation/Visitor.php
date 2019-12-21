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
            if ($node->stmts === null) {
                return null;
            }

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

        // For short-circuiting operators, insert a tracing call into one branch.
        if ($node instanceof Expr\BinaryOp\BooleanAnd ||
            $node instanceof Expr\BinaryOp\BooleanOr ||
            $node instanceof Expr\BinaryOp\LogicalAnd ||
            $node instanceof Expr\BinaryOp\LogicalOr ||
            $node instanceof Expr\BinaryOp\Coalesce ||
            $node instanceof Expr\AssignOp\Coalesce
        ) {
            $node->right = $this->generateTracingCall($node->right);
            return null;
        }

        // Same as previous case, just different subnode name.
        if ($node instanceof Expr\Ternary) {
            $node->else = $this->generateTracingCall($node->else);
            return null;
        }

        // Wrap the yield, so that a tracing call occurs after the yield resumes.
        if ($node instanceof Expr\Yield_ ||
            $node instanceof Expr\YieldFrom
        ) {
            return $this->generateTracingCall($node);
        }

        // TODO: Comparison instrumentation?
        // TODO: Avoid redundant instrumentation?
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
        $context = new Node\Name\FullyQualified($this->context->runtimeContextName);
        $edgesVar = new Expr\StaticPropertyFetch($context, 'edges');
        $edgesKeyVar = new Expr\ArrayDimFetch($edgesVar, $keyVar);
        $prevBlockVar = new Expr\StaticPropertyFetch($context, 'prevBlock');
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

    private function generateTracingCall(Expr $origExpr): Expr {
        $context = new Node\Name\FullyQualified($this->context->runtimeContextName);
        $blockIndex = new Scalar\LNumber($this->context->getNewBlockIndex());
        return new Expr\StaticCall($context, 'traceBlock', [
            new Node\Arg($blockIndex),
            new Node\Arg($origExpr),
        ]);
    }
}