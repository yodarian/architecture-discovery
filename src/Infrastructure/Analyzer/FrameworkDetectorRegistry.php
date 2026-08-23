<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

/**
 * Selects which FrameworkPatternDetectors should run against a project, gated by
 * composer.json's require when it names a known framework package.
 */
final class FrameworkDetectorRegistry
{
    /**
     * @param FrameworkPatternDetector[] $detectors
     */
    public function __construct(
        private array $detectors = [new CakePhpAnalyzer(), new LaravelAnalyzer()]
    ) {
    }

    /**
     * @return FrameworkPatternDetector[]
     */
    public function detectorsFor(string $projectPath): array
    {
        $required = $this->readComposerRequireKeys($projectPath);
        if ($required === null || $required === []) {
            return $this->detectors;
        }

        $matched = array_values(array_filter(
            $this->detectors,
            static fn(FrameworkPatternDetector $detector): bool => isset($required[$detector->framework()->composerPackage()])
        ));

        return $matched !== [] ? $matched : $this->detectors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readComposerRequireKeys(string $projectPath): ?array
    {
        $composerFile = rtrim($projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composerFile)) {
            return null;
        }

        $content = file_get_contents($composerFile);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['require']) || !is_array($data['require'])) {
            return null;
        }

        return $data['require'];
    }
}
