<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

use ArchitectureDiscovery\Domain\Model\Dependency;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Detects CakePHP ORM associations and dynamic model access from PHP ASTs.
 */
final class CakePhpAnalyzer extends AbstractAstPatternDetector
{
    /** @var string[] */
    private const ORM_METHODS = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];

    /** @var string[] */
    private const DYNAMIC_METHODS = ['fetchTable', 'loadModel'];

    public function framework(): Framework
    {
        return Framework::CakePhp;
    }

    protected function matchNode(Node $node, string $namespace, string $className): ?DetectedRelationship
    {
        if (!$node instanceof Expr\MethodCall || !$node->name instanceof Node\Identifier || !$this->isThisCall($node)) {
            return null;
        }

        $method = $node->name->toString();
        if (in_array($method, self::ORM_METHODS, true)) {
            $type = Dependency::TYPE_ORM_RELATION;
            $weight = 3;
            $metadata = ['method' => $method, 'relation' => $method];
        } elseif (in_array($method, self::DYNAMIC_METHODS, true)) {
            $type = Dependency::TYPE_DYNAMIC_CALL;
            $weight = 2;
            $metadata = ['method' => $method];
        } else {
            return null;
        }

        [$target, $isStatic] = $this->extractTarget($node->args[0]->value ?? null);
        $metadata['static'] = $isStatic;

        return new DetectedRelationship(
            $namespace ? $namespace . '\\' . $className : $className,
            $target,
            $type,
            $weight,
            metadata: $metadata
        );
    }
}
