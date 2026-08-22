<?php
namespace ArchitectureDiscovery\Domain\Model;

/**
 * Architecture represents the complete analysis model of a PHP project.
 * It is the canonical artifact for all downstream consumers.
 */
final class Architecture
{
    private ProjectMetadata $metadata;
    /** @var ClassEntity[] */
    private array $classes = [];
    /** @var Dependency[] */
    private array $dependencies = [];
    /** @var array<string, mixed> */
    private array $metrics = [];
    /** @var array<int, array<string, mixed>> */
    private array $clusters = [];

    public function __construct(ProjectMetadata $metadata)
    {
        $this->metadata = $metadata;
    }

    public function getMetadata(): ProjectMetadata
    {
        return $this->metadata;
    }

    public function addClass(ClassEntity $class): void
    {
        $this->classes[$class->getFullyQualifiedName()] = $class;
    }

    /**
     * @return ClassEntity[]
     */
    public function getClasses(): array
    {
        $classes = array_values($this->classes);
        usort($classes, fn(ClassEntity $left, ClassEntity $right) =>
            strcmp($left->getFullyQualifiedName(), $right->getFullyQualifiedName())
        );
        return $classes;
    }

    public function getClass(string $fullyQualifiedName): ?ClassEntity
    {
        return $this->classes[$fullyQualifiedName] ?? null;
    }

    public function addDependency(Dependency $dependency): void
    {
        $key = $dependency->getFrom()->getFullyQualifiedName() . '->' . $dependency->getTo()->getFullyQualifiedName() . '::' . $dependency->getType();
        $this->dependencies[$key] = $dependency;
    }

    /**
     * @return Dependency[]
     */
    public function getDependencies(): array
    {
        $dependencies = array_values($this->dependencies);
        usort($dependencies, fn(Dependency $left, Dependency $right) => strcmp(
            $left->getFrom()->getFullyQualifiedName() . '|' . $left->getTo()->getFullyQualifiedName() . '|' . $left->getType(),
            $right->getFrom()->getFullyQualifiedName() . '|' . $right->getTo()->getFullyQualifiedName() . '|' . $right->getType()
        ));
        return $dependencies;
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function setMetrics(array $metrics): void
    {
        $this->metrics = $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @param array<int, array<string, mixed>> $clusters
     */
    public function setClusters(array $clusters): void
    {
        $this->clusters = $clusters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClusters(): array
    {
        return $this->clusters;
    }

    /**
     * Serialize to array suitable for JSON output
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->metadata->getVersion(),
            'generatedAt' => $this->metadata->getGeneratedAt()->format(\DateTimeInterface::ATOM),
            'project' => $this->metadata->toArray(),
            'classes' => array_map(fn(ClassEntity $c) => $c->toArray(), $this->getClasses()),
            'dependencies' => array_map(fn(Dependency $d) => $d->toArray(), $this->getDependencies()),
            'metrics' => $this->metrics,
            'clusters' => $this->clusters,
        ];
    }
}
