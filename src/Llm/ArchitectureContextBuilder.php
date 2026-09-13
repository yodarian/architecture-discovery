<?php
namespace ArchitectureDiscovery\Llm;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Reporting\View\ArchitectureViewBuilder;

/**
 * Reduces the architecture model to the data permitted for an LLM request.
 */
final class ArchitectureContextBuilder
{
    public function __construct(private ArchitectureViewBuilder $viewBuilder = new ArchitectureViewBuilder())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Architecture $architecture): array
    {
        $view = $this->viewBuilder->build($architecture);
        return [
            'modelVersion' => $view->getModelVersion(),
            'project' => $view->getProject(),
            'summary' => $view->getMetrics(),
            'classes' => $view->getClasses(),
            'dependencies' => $view->getDependencies(),
            'clusters' => $view->getClusters(),
            'moduleCandidates' => $view->getModuleCandidates(),
            'classMemberships' => $view->getClassMemberships(),
            'unassignedClasses' => $view->getUnassignedClasses(),
        ];
    }
}
