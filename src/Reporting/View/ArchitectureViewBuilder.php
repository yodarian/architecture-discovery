<?php
namespace ArchitectureDiscovery\Reporting\View;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;

/**
 * Builds the normalized ArchitectureView from a domain Architecture instance.
 */
final class ArchitectureViewBuilder
{
    public function build(Architecture $architecture): ArchitectureView
    {
        $metadata = $architecture->getMetadata();

        return new ArchitectureView(
            $metadata->getVersion(),
            [
                'name' => $metadata->getName(),
                'version' => $metadata->getVersion(),
            ],
            array_map(
                static fn(ClassEntity $class): array => [
                    'fqn' => $class->getFullyQualifiedName(),
                    'type' => $class->getType(),
                    'namespace' => $class->getNamespace(),
                    'name' => $class->getName(),
                    'abstract' => $class->isAbstract(),
                ],
                $architecture->getClasses()
            ),
            array_map(
                static fn(Dependency $dependency): array => [
                    'from' => $dependency->getFrom()->getFullyQualifiedName(),
                    'to' => $dependency->getTo()->getFullyQualifiedName(),
                    'type' => $dependency->getType(),
                    'weight' => $dependency->getWeight(),
                    'metadata' => $dependency->getMetadata(),
                ],
                $architecture->getDependencies()
            ),
            $architecture->getMetrics(),
            $architecture->getClusters()
        );
    }
}
