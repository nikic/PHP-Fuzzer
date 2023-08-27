<?php declare(strict_types=1);

namespace PhpFuzzer\Instrumentation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor for inserting instrumentation code. It uses manual code insertion via a
 * MutableString in order to preserve line numbers from the original code.
 */
final class Visitor extends NodeVisitorAbstract {
    private Context $context;

    public function __construct(Context $context) {
        $this->context = $context;
    }

    public function leaveNode(Node $node) {
        // In these cases it is sufficient to insert a stub at the start.
        if ($node instanceof Expr\Closure ||
            $node instanceof Stmt\Case_ ||
            $node instanceof Stmt\Catch_ ||
            $node instanceof Stmt\ClassMethod ||
            $node instanceof Stmt\Else_ ||
            $node instanceof Stmt\ElseIf_ ||
            $node instanceof Stmt\Finally_ ||
            $node instanceof Stmt\Function_
        ) {
            if ($node->stmts === null) {
                return null;
            }

            $this->insertInlineBlockStubInStmts($node);
            return null;
        }

        // In these cases we should additionally insert one after the node.
        if ($node instanceof Stmt\Do_ ||
            $node instanceof Stmt\If_ ||
            $node instanceof Stmt\For_ ||
            $node instanceof Stmt\Foreach_ ||
            $node instanceof Stmt\While_
        ) {
            $this->insertInlineBlockStubInStmts($node);
            $this->appendInlineBlockStub($node);
            return null;
        }

        // In these cases we need to insert one after the node only.
        if ($node instanceof Stmt\Label ||
            $node instanceof Stmt\Switch_ ||
            $node instanceof Stmt\TryCatch
        ) {
            $this->appendInlineBlockStub($node);
            return null;
        }

        // For short-circuiting operators, insert a tracing call into one branch.
        if ($node instanceof Expr\BinaryOp\BooleanAnd ||
            $node instanceof Expr\BinaryOp\BooleanOr ||
            $node instanceof Expr\BinaryOp\LogicalAnd ||
            $node instanceof Expr\BinaryOp\LogicalOr ||
            $node instanceof Expr\BinaryOp\Coalesce
        ) {
            $this->insertTracingCall($node->right);
            return null;
        }

        // Same as previous case, just different subnode name.
        if ($node instanceof Expr\Ternary) {
            $this->insertTracingCall($node->else);
            return null;
        }

        // Same as previous case, just different subnode name.
        if ($node instanceof Expr\AssignOp\Coalesce) {
            $this->insertTracingCall($node->expr);
            return null;
        }

        // Instrument call to arrow function.
        if ($node instanceof Expr\ArrowFunction) {
            $this->insertTracingCall($node->expr);
            return null;
        }

        if ($node instanceof Node\MatchArm) {
            $this->insertTracingCall($node->body);
            return null;
        }

        // Wrap the yield, so that a tracing call occurs after the yield resumes.
        if ($node instanceof Expr\Yield_ ||
            $node instanceof Expr\YieldFrom
        ) {
            $this->insertTracingCall($node);
            return null;
        }

        // TODO: Comparison instrumentation?
        // TODO: Avoid redundant instrumentation?
        return null;
    }

    private function insertInlineBlockStubInStmts(Node $node): void {
        $stub = $this->generateInlineBlockStub($node->getStartFilePos());
        $stmts = $node->stmts;
        if (!empty($stmts)) {
            /** @var Stmt $firstStmt */
            $firstStmt = $stmts[0];
            $startPos = $firstStmt->getStartFilePos();
            $endPos = $firstStmt->getEndFilePos();
            // Wrap the statement in {} in case this is a single "stmt;" block.
            $this->context->code->insert($startPos, "{ $stub ", 0);
            $this->context->code->insert($endPos + 1, " }", 1);
            return;
        }

        // We have an empty statement list. This may be represented as "{}", ";"
        // or, in case of a "case" statement, nothing.
        $endPos = $node->getEndFilePos();
        $endChar = $this->context->code->getOrigString()[$endPos];
        if ($endChar === '}') {
            $this->context->code->insert($endPos, " $stub ");
        } else if ($endChar === ';') {
            $this->context->code->replace($endPos, 1, "{ $stub }");
        } else if ($endChar === ':') {
            $this->context->code->insert($endPos + 1, " $stub");
        } else {
            throw new \Error("Unexpected end char '$endChar'");
        }
    }

    private function appendInlineBlockStub(Stmt $stmt): void {
        $endPos = $stmt->getEndFilePos();
        $stub = $this->generateInlineBlockStub($endPos);
        $this->context->code->insert($endPos + 1, " $stub");
    }

    private function generateInlineBlockStub(int $pos): string {
        // We generate the following code:
        //   $___key = (Context::$prevBlock << 28) | BLOCK_INDEX;
        //   Context::$edges[$___key] = (Context::$edges[$___key] ?? 0) + 1;
        //   Context::$prevBlock = BLOCK_INDEX;
        // We use a 28-bit block index to leave 8-bits to encode a logarithmic trip count.
        // TODO: When I originally picked this format, I forgot about the initialization issue.
        // TODO: It probably makes sense to switch this to something that can be pre-initialized.
        $blockIndex = $this->context->getNewBlockIndex($pos);
        $contextName = $this->context->runtimeContextName;
        return "\$___key = (\\$contextName::\$prevBlock << 28) | $blockIndex; "
            . "\\$contextName::\$edges[\$___key] = (\\$contextName::\$edges[\$___key] ?? 0) + 1; "
            . "\\$contextName::\$prevBlock = $blockIndex;";
    }

    private function insertTracingCall(Expr $expr): void {
        $startPos = $expr->getStartFilePos();
        $endPos = $expr->getEndFilePos();
        $blockIndex = $this->context->getNewBlockIndex($startPos);
        $contextName = $this->context->runtimeContextName;

        $this->context->code->insert($startPos, "\\$contextName::traceBlock($blockIndex, ", 1);
        $this->context->code->insert($endPos + 1, ")", 0);
    }
}
