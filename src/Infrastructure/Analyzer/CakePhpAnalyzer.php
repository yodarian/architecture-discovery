<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

use ArchitectureDiscovery\Domain\Model\Dependency;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

/**
 * Detects CakePHP ORM associations and dynamic model access from PHP ASTs.
 */
final class CakePhpAnalyzer
{
    /** @var string[] */
    private const ORM_METHODS = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];

    /** @var string[] */
    private const DYNAMIC_METHODS = ['fetchTable', 'loadModel'];

    /**
     * @return array<int, array{from: string, target: string|null, type: string, weight: int, metadata: array<string, mixed>}>
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
     * @param array<int, array{from: string, target: string|null, type: string, weight: int, metadata: array<string, mixed>}> $relationships
     */
    private function collectFromNode(Node $node, string $namespace, string $className, array &$relationships): void
    {
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $value = $node->$subNodeName;
            $children = is_array($value) ? $value : [$value];
            foreach ($children as $child) {
                if ($child instanceof Expr\MethodCall) {
                    $this->addMethodRelationship($child, $namespace, $className, $relationships);
                }
                if ($child instanceof Node) {
                    $this->collectFromNode($child, $namespace, $className, $relationships);
                }
            }
        }
    }

    /**
     * @param array<int, array{from: string, target: string|null, type: string, weight: int, metadata: array<string, mixed>}> $relationships
     */
    private function addMethodRelationship(
        Expr\MethodCall $call,
        string $namespace,
        string $className,
        array &$relationships
    ): void {
        if (!$call->name instanceof Node\Identifier || !$this->isThisCall($call)) {
            return;
        }

        $method = $call->name->toString();
        $type = null;
        $weight = 0;
        $metadata = ['method' => $method, 'static' => false];

        if (in_array($method, self::ORM_METHODS, true)) {
            $type = Dependency::TYPE_ORM_RELATION;
            $weight = 3;
            $metadata['relation'] = $method;
        } elseif (in_array($method, self::DYNAMIC_METHODS, true)) {
            $type = Dependency::TYPE_DYNAMIC_CALL;
            $weight = 2;
        } else {
            return;
        }

        $target = $call->args[0]->value ?? null;
        if ($target instanceof String_) {
            $metadata['static'] = true;
            $target = $target->value;
        } else {
            $target = null;
        }

        $relationships[] = [
            'from' => $namespace ? $namespace . '\\' . $className : $className,
            'target' => $target,
            'type' => $type,
            'weight' => $weight,
            'metadata' => $metadata,
        ];
    }

    private function isThisCall(Expr\MethodCall $call): bool
    {
        return $call->var instanceof Expr\Variable && $call->var->name === 'this';
    }
}
