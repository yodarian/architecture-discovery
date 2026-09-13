<?php
namespace ArchitectureDiscovery\Reporting\View;

/**
 * ArchitectureView is the stable, normalized shape renderers and the LLM context
 * builder consume, decoupled from Architecture's internal object graph and from
 * the versioned architecture.json schema.
 */
final class ArchitectureView
{
    /**
     * @param array{name: string, version: string} $project
    * @param array<int, array{fqn: string, type: string, namespace: string, name: string, abstract: bool}> $classes
    * @param array<int, array{from: string, to: string, type: string, weight: int, metadata: array<string, mixed>}> $dependencies
     * @param array<string, mixed> $metrics
     * @param array<int, array<string, mixed>> $clusters
    * @param array<int, array<string, mixed>> $moduleCandidates
    * @param array<string, array<string, mixed>> $classMemberships
    * @param string[] $unassignedClasses
     */
    public function __construct(
        private string $modelVersion,
        private array $project,
        private array $classes,
        private array $dependencies,
        private array $metrics,
        private array $clusters,
        private array $moduleCandidates = [],
        private array $classMemberships = [],
        private array $unassignedClasses = []
    ) {
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }

    /**
     * @return array{name: string, version: string}
     */
    public function getProject(): array
    {
        return $this->project;
    }

    /**
    * @return array<int, array{fqn: string, type: string, namespace: string, name: string, abstract: bool}>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
    * @return array<int, array{from: string, to: string, type: string, weight: int, metadata: array<string, mixed>}>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClusters(): array
    {
        return $this->clusters;
    }

    /** @return array<int, array<string, mixed>> */
    public function getModuleCandidates(): array
    {
        return $this->moduleCandidates;
    }

    /** @return array<string, array<string, mixed>> */
    public function getClassMemberships(): array
    {
        return $this->classMemberships;
    }

    /** @return string[] */
    public function getUnassignedClasses(): array
    {
        return $this->unassignedClasses;
    }
}
