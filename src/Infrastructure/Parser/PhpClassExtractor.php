<?php
namespace ArchitectureDiscovery\Infrastructure\Parser;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ArchitectureDiscovery\Domain\Model\ClassEntity;

/**
 * PhpClassExtractor extracts class, interface, and trait definitions from PHP files.
 */
final class PhpClassExtractor
{
    private Parser $parser;
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $factory = new ParserFactory();
        $this->parser = $factory->createForNewestSupportedVersion();
    }

    /**
     * Extract class definitions from a PHP file.
     *
     * @return ClassEntity[]
     * @throws \PhpParser\Error
     */
    public function extractFromFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException("File does not exist: {$filePath}");
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (\PhpParser\Error $e) {
            // Return empty on parse error - we'll log but continue
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $classes = [];
        $namespace = '';

        foreach ($ast as $node) {
            if ($node instanceof Stmt\Namespace_) {
                $namespace = $node->name ? $this->nameToString($node->name) : '';
                $classes = array_merge($classes, $this->extractClassesFromNamespace($node, $namespace, $filePath));
            } elseif ($node instanceof Stmt\Class_ || $node instanceof Stmt\Interface_ || $node instanceof Stmt\Trait_) {
                $classes[] = $this->createClassEntity($node, $namespace, $filePath);
            }
        }

        return $classes;
    }

    /**
     * Extract classes from a namespace node.
     *
     * @return ClassEntity[]
     */
    private function extractClassesFromNamespace(Stmt\Namespace_ $namespace, string $namespace_name, string $filePath): array
    {
        $classes = [];
        $uses = $this->extractUses($namespace);
        foreach ($namespace->stmts as $node) {
            if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Interface_ || $node instanceof Stmt\Trait_) {
                $classes[] = $this->createClassEntity($node, $namespace_name, $filePath, $uses);
            }
        }
        return $classes;
    }

    /**
     * Create a ClassEntity from an AST node.
     */
    private function createClassEntity(Stmt\ClassLike $node, string $namespace, string $filePath, array $uses = []): ClassEntity
    {
        $type = match (true) {
            $node instanceof Stmt\Class_ => ClassEntity::TYPE_CLASS,
            $node instanceof Stmt\Interface_ => ClassEntity::TYPE_INTERFACE,
            $node instanceof Stmt\Trait_ => ClassEntity::TYPE_TRAIT,
            default => ClassEntity::TYPE_CLASS,
        };

        $fullyQualifiedName = $namespace ? $namespace . '\\' . (string) $node->name : (string) $node->name;
        $relativePath = $this->getRelativeFilePath($filePath);

        $extends = null;
        $interfaces = [];
        $traits = [];
        $isAbstract = false;

        if ($node instanceof Stmt\Class_) {
            if ($node->extends) {
                $extends = $this->resolveName($node->extends, $namespace, $uses);
            }
            foreach ($node->implements as $implement) {
                $interfaces[] = $this->resolveName($implement, $namespace, $uses);
            }
            $isAbstract = $node->isAbstract();
        } elseif ($node instanceof Stmt\Interface_) {
            foreach ($node->extends as $extend) {
                $interfaces[] = $this->resolveName($extend, $namespace, $uses);
            }
        }

        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_) {
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Stmt\TraitUse) {
                    foreach ($stmt->traits as $trait) {
                        $traits[] = $this->resolveName($trait, $namespace, $uses);
                    }
                }
            }
        }

        return new ClassEntity(
            $fullyQualifiedName,
            $type,
            $namespace,
            (string)$node->name,
            $relativePath,
            $node->getLine(),
            $interfaces,
            $traits,
            $extends,
            $isAbstract
        );
    }

    private function nameToString(?Name $name): string
    {
        if ($name === null) {
            return '';
        }
        return $name->toString();
    }

    /**
     * @return array<string, string>
     */
    private function extractUses(Stmt\Namespace_ $namespace): array
    {
        $uses = [];
        foreach ($namespace->stmts as $statement) {
            if (!$statement instanceof Stmt\Use_) {
                continue;
            }

            foreach ($statement->uses as $use) {
                $importedName = $use->name->toString();
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $uses[$alias] = $importedName;
            }
        }

        return $uses;
    }

    /**
     * Resolve a type name using PHP namespace and import rules.
     *
     * @param array<string, string> $uses
     */
    private function resolveName(Name $name, string $namespace, array $uses): string
    {
        $value = $name->toString();
        if ($name->isFullyQualified()) {
            return $value;
        }

        $parts = $name->getParts();
        $firstPart = $parts[0] ?? '';
        if (isset($uses[$firstPart])) {
            return $uses[$firstPart] . (count($parts) > 1 ? '\\' . implode('\\', array_slice($parts, 1)) : '');
        }

        return $namespace ? $namespace . '\\' . $value : $value;
    }

    private function getRelativeFilePath(string $filePath): string
    {
        $filePath = realpath($filePath) ?: $filePath;
        if (strpos($filePath, $this->projectRoot) === 0) {
            return substr($filePath, strlen($this->projectRoot) + 1);
        }
        return $filePath;
    }
}
