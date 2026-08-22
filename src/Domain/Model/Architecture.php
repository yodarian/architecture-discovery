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
        return array_values($this->classes);
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
        return array_values($this->dependencies);
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
        ];
    }
}
