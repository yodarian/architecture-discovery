<?php
namespace ArchitectureDiscovery\Llm;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Reduces the architecture model to the data permitted for an LLM request.
 */
final class ArchitectureContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Architecture $architecture): array
    {
        $data = $architecture->toArray();
        return [
            'modelVersion' => $data['version'],
            'project' => [
                'name' => $data['project']['name'],
                'version' => $data['project']['version'],
            ],
            'summary' => $data['metrics'],
            'classes' => array_map(
                static fn(array $class): array => [
                    'fqn' => $class['fqn'],
                    'type' => $class['type'],
                    'namespace' => $class['namespace'],
                    'name' => $class['name'],
                ],
                $data['classes']
            ),
            'dependencies' => array_map(
                static fn(array $dependency): array => [
                    'from' => $dependency['from'],
                    'to' => $dependency['to'],
                    'type' => $dependency['type'],
                    'weight' => $dependency['weight'],
                ],
                $data['dependencies']
            ),
            'clusters' => $data['clusters'],
        ];
    }
}
