<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

/**
 * Owns the common "parse file, walk namespace/class nodes, recurse into every
 * subnode" traversal shared by every FrameworkPatternDetector. Subclasses only
 * decide whether a single AST node is a pattern they recognize.
 */
abstract class AbstractAstPatternDetector implements FrameworkPatternDetector
{
    /**
     * @return DetectedRelationship[]
     */
    public function analyzeFile(string $filePath): array
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return [];
        }

        try {
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code);
        } catch (\PhpParser\Error) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $relationships = [];
        foreach ($ast as $node) {
            if ($node instanceof Stmt\Namespace_) {
                $namespace = $node->name?->toString() ?? '';
                $statements = $node->stmts;
            } else {
                $namespace = '';
                $statements = [$node];
            }

            foreach ($statements as $statement) {
                if ($statement instanceof Stmt\Class_) {
                    $this->collectFromNode($statement, $namespace, (string) $statement->name, $relationships);
                }
            }
        }

        return $relationships;
    }

    /**
     * Decide whether a single AST node is a pattern this detector recognizes.
     */
    abstract protected function matchNode(Node $node, string $namespace, string $className): ?DetectedRelationship;

    /**
     * @param DetectedRelationship[] $relationships
     */
    private function collectFromNode(Node $node, string $namespace, string $className, array &$relationships): void
    {
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $value = $node->$subNodeName;
            $children = is_array($value) ? $value : [$value];
            foreach ($children as $child) {
                if ($child instanceof Node) {
                    $relationship = $this->matchNode($child, $namespace, $className);
                    if ($relationship !== null) {
                        $relationships[] = $relationship;
                    }
                    $this->collectFromNode($child, $namespace, $className, $relationships);
                }
            }
        }
    }

    protected function isThisCall(Expr\MethodCall $call): bool
    {
        return $call->var instanceof Expr\Variable && $call->var->name === 'this';
    }

    /**
     * Extracts a static target name from a call argument, recognizing a plain string
     * literal and, optionally, a `X::class` constant reference.
     *
     * @return array{0: ?string, 1: bool}
     */
    protected function extractTarget(?Node $argument, bool $allowClassConstFetch = false): array
    {
        if ($allowClassConstFetch
            && $argument instanceof Expr\ClassConstFetch
            && $argument->name instanceof Node\Identifier
            && strtolower($argument->name->toString()) === 'class'
            && $argument->class instanceof Node\Name
        ) {
            return [$argument->class->toString(), true];
        }

        if ($argument instanceof String_) {
            return [$argument->value, true];
        }

        return [null, false];
    }
}
