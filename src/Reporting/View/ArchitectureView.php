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
     * @param array<int, array{fqn: string, type: string, namespace: string, name: string}> $classes
     * @param array<int, array{from: string, to: string, type: string, weight: int}> $dependencies
     * @param array<string, mixed> $metrics
     * @param array<int, array<string, mixed>> $clusters
     */
    public function __construct(
        private string $modelVersion,
        private array $project,
        private array $classes,
        private array $dependencies,
        private array $metrics,
        private array $clusters
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
     * @return array<int, array{fqn: string, type: string, namespace: string, name: string}>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @return array<int, array{from: string, to: string, type: string, weight: int}>
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
}
