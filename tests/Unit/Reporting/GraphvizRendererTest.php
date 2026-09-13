<?php
namespace ArchitectureDiscovery\Tests\Unit\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Reporting\GraphvizRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GraphvizRendererTest extends TestCase
{
    public function testStylesEdgesByTargetAbstractionAndLabelsRelationshipTypesWithLegend(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));
        $source = new ClassEntity('App\\Source', 'class', 'App', 'Source', 'src/Source.php', 1);
        $interface = new ClassEntity('App\\Contract', 'interface', 'App', 'Contract', 'src/Contract.php', 1);
        $concrete = new ClassEntity('App\\Concrete', 'class', 'App', 'Concrete', 'src/Concrete.php', 1);
        $abstract = new ClassEntity('App\\AbstractThing', 'class', 'App', 'AbstractThing', 'src/AbstractThing.php', 1, [], [], null, true);
        $framework = new ClassEntity('Framework\\Kernel', 'class', 'Framework', 'Kernel', 'vendor/Kernel.php', 1);
        foreach ([$source, $interface, $concrete, $abstract, $framework] as $class) {
            $architecture->addClass($class);
        }
        $architecture->addDependency(new Dependency($source, $interface, Dependency::TYPE_IMPLEMENTS));
        $architecture->addDependency(new Dependency($source, $concrete, Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($source, $abstract, Dependency::TYPE_PARAMETER_TYPE));
        $architecture->addDependency(new Dependency($source, $framework, Dependency::TYPE_USES, 1, ['framework' => 'external']));
        $architecture->setModuleCandidates([['namespace' => ['driftingMembers' => [['class' => 'App\\Source']]]]]);

        $dot = (new GraphvizRenderer())->renderDot($architecture);

        $this->assertStringContainsString('color="#38a169"', $dot);
        $this->assertStringContainsString('color="#c53030"', $dot);
        $this->assertStringContainsString('color="#b7791f"', $dot);
        $this->assertStringContainsString('color="#718096"', $dot);
        $this->assertStringContainsString('label="implements"', $dot);
        $this->assertStringContainsString('label="parameter_type"', $dot);
        $this->assertStringContainsString('Legend', $dot);
        $this->assertStringContainsString('Interface target', $dot);
        $this->assertStringContainsString('Namespace drift', $dot);
        $this->assertStringContainsString('"App\\\\Source" [label="Source", style="dotted"]', $dot);
    }

    public function testFallbackSvgIncludesSemanticLegend(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));
        $architecture->addClass(new ClassEntity('App\\Source', 'class', 'App', 'Source', 'src/Source.php', 1));

        $svg = (new GraphvizRenderer())->renderFallbackSvg($architecture);

        $this->assertStringContainsString('Interface target: green', $svg);
        $this->assertStringContainsString('Concrete target: red', $svg);
        $this->assertStringContainsString('Namespace drift: dotted', $svg);
    }
}