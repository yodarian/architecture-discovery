<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

use ArchitectureDiscovery\Domain\Model\Dependency;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Detects Laravel Eloquent relationships and dynamic container resolution from PHP ASTs.
 */
final class LaravelAnalyzer extends AbstractAstPatternDetector
{
    /** @var string[] */
    private const RELATION_METHODS = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];

    /** @var string[] */
    private const DYNAMIC_FUNCTIONS = ['app', 'resolve'];

    public function framework(): Framework
    {
        return Framework::Laravel;
    }

    protected function matchNode(Node $node, string $namespace, string $className): ?DetectedRelationship
    {
        $from = $namespace ? $namespace . '\\' . $className : $className;

        if ($node instanceof Expr\MethodCall
            && $node->name instanceof Node\Identifier
            && $this->isThisCall($node)
            && in_array($node->name->toString(), self::RELATION_METHODS, true)
        ) {
            $method = $node->name->toString();
            [$target, $isStatic] = $this->extractTarget($node->args[0]->value ?? null, allowClassConstFetch: true);

            return new DetectedRelationship(
                $from,
                $target,
                Dependency::TYPE_ORM_RELATION,
                3,
                metadata: ['method' => $method, 'relation' => $method, 'static' => $isStatic]
            );
        }

        if ($node instanceof Expr\FuncCall
            && $node->name instanceof Node\Name
            && in_array($node->name->toString(), self::DYNAMIC_FUNCTIONS, true)
        ) {
            $function = $node->name->toString();
            [$target, $isStatic] = $this->extractTarget($node->args[0]->value ?? null, allowClassConstFetch: true);

            return new DetectedRelationship(
                $from,
                $target,
                Dependency::TYPE_DYNAMIC_CALL,
                2,
                metadata: ['method' => $function, 'static' => $isStatic]
            );
        }

        return null;
    }
}
