<?php
namespace ArchitectureDiscovery\Analysis;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;

/**
 * Derives explainable feature candidates from class names and locations.
 */
final class ModuleCandidateDiscoverer
{
    /** @var array<string, true> */
    private const STOP_WORDS = [
        'app' => true,
        'application' => true,
        'class' => true,
        'classes' => true,
        'domain' => true,
        'entity' => true,
        'entities' => true,
        'http' => true,
        'infrastructure' => true,
        'interface' => true,
        'interfaces' => true,
        'model' => true,
        'models' => true,
        'persistence' => true,
        'repository' => true,
        'repositories' => true,
        'php' => true,
        'service' => true,
        'services' => true,
        'shared' => true,
        'src' => true,
        'table' => true,
        'tables' => true,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discover(Architecture $architecture): array
    {
        /** @var array<string, array<string, array<string, true>>> $tokenEvidence */
        $tokenEvidence = [];
        foreach ($architecture->getClasses() as $class) {
            $sources = [
                'name' => $this->normalizeTokens($class->getName()),
                'namespace' => $this->normalizeTokens($class->getNamespace()),
                'path' => $this->normalizeTokens($class->getFile()),
            ];
            foreach ($sources as $source => $tokens) {
                foreach ($tokens as $token) {
                    if (isset(self::STOP_WORDS[$token])) {
                        continue;
                    }
                    $tokenEvidence[$token][$source][$class->getFullyQualifiedName()] = true;
                }
            }
        }

        $candidateTokens = [];
        foreach ($tokenEvidence as $token => $sources) {
            $members = [];
            foreach ($sources as $classNames) {
                foreach (array_keys($classNames) as $className) {
                    $members[$className] = true;
                }
            }
            if (count($members) >= 2) {
                $candidateTokens[$token] = true;
            }
        }

        $candidates = [];
        $unassigned = [];
        foreach ($architecture->getClasses() as $class) {
            $className = $class->getFullyQualifiedName();
            $matches = [];
            foreach ($tokenEvidence as $token => $sources) {
                if (isset($candidateTokens[$token]) && $this->hasClassMatch($sources, $className)) {
                    $matches[$token] = $sources;
                }
            }
            if ($matches === []) {
                $unassigned[] = $className;
            }
        }

        foreach ($tokenEvidence as $token => $sources) {
            $members = [];
            foreach ($sources as $classNames) {
                foreach (array_keys($classNames) as $className) {
                    $members[$className] = true;
                }
            }
            if (!isset($candidateTokens[$token])) {
                continue;
            }

            $memberNames = array_keys($members);
            sort($memberNames);
            $signals = [];
            foreach (['name', 'namespace', 'path'] as $source) {
                if (isset($sources[$source])) {
                    $names = array_keys($sources[$source]);
                    sort($names);
                    $signals[$source] = $names;
                }
            }
            $entrypoints = array_values(array_filter(
                $memberNames,
                static fn(string $className): bool => str_ends_with($className, 'Controller')
            ));
            $confidence = round(
                (isset($signals['name']) ? 0.5 : 0.0)
                + (isset($signals['namespace']) ? 0.3 : 0.0)
                + (isset($signals['path']) ? 0.1 : 0.0),
                2
            );

            $candidates[] = [
                'id' => 'module-' . $token,
                'name' => ucfirst($token),
                'coreMembers' => $memberNames,
                'relatedMembers' => [],
                'entrypoints' => $entrypoints,
                'confidence' => $confidence,
                'evidence' => [
                    'token' => $token,
                    'signals' => $signals,
                ],
            ];
        }

        usort($candidates, static fn(array $left, array $right): int => strcmp($left['id'], $right['id']));
        sort($unassigned);
        $architecture->setUnassignedClasses($unassigned);

        return $candidates;
    }

    /**
     * Split naming conventions without matching incidental substrings.
     *
     * @return string[]
     */
    public function normalizeTokens(string $value): array
    {
        $value = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $value) ?? $value;
        $value = preg_replace('/([a-z\d])([A-Z])/', '$1 $2', $value) ?? $value;
        $parts = preg_split('/[^a-zA-Z\d]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_map('strtolower', $parts)));
    }

    /**
     * @param array<string, array<string, true>> $sources
     */
    private function hasClassMatch(array $sources, string $className): bool
    {
        foreach ($sources as $classNames) {
            if (isset($classNames[$className])) {
                return true;
            }
        }
        return false;
    }
}