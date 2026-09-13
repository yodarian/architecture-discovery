<?php
namespace ArchitectureDiscovery\Analysis;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;

/**
 * Derives explainable feature candidates from class names and locations.
 */
final class ModuleCandidateDiscoverer
{
    /**
     * @param array<string, string[]> $namespacePatterns Candidate token to expected namespace patterns.
     */
    public function __construct(private array $namespacePatterns = [])
    {
    }

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
        $roles = [];
        foreach ($architecture->getClasses() as $class) {
            $roles[$class->getFullyQualifiedName()] = $this->classifyRole($class);
        }

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
                    if (!$this->isCoreEligible($roles[$className])) {
                        continue;
                    }
                    $members[$className] = true;
                }
            }
            if (count($members) >= 2) {
                $candidateTokens[$token] = true;
            }
        }

        $candidates = [];
        foreach ($tokenEvidence as $token => $sources) {
            $members = [];
            foreach ($sources as $classNames) {
                foreach (array_keys($classNames) as $className) {
                    if (!$this->isCoreEligible($roles[$className])) {
                        continue;
                    }
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
            $supportingMembers = [];
            foreach ($sources as $classNames) {
                foreach (array_keys($classNames) as $className) {
                    if (!$this->isCoreEligible($roles[$className])) {
                        $supportingMembers[$className] = true;
                    }
                }
            }
            $supportingMembers = array_keys($supportingMembers);
            sort($supportingMembers);
            $expectedPatterns = $this->namespacePatterns[$token] ?? ['*\\' . ucfirst($token)];
            $alignedMembers = [];
            $driftingMembers = [];
            foreach ($memberNames as $className) {
                $class = $architecture->getClass($className);
                if ($class === null) {
                    continue;
                }
                if ($this->matchesNamespacePattern($class->getNamespace(), $expectedPatterns)) {
                    $alignedMembers[] = $className;
                    continue;
                }
                $driftingMembers[] = [
                    'class' => $className,
                    'namespace' => $class->getNamespace(),
                    'file' => $class->getFile(),
                    'role' => $roles[$className],
                    'confidence' => 0.5,
                    'evidence' => [
                        'token' => $token,
                        'reason' => 'namespace_pattern_mismatch',
                    ],
                    'suggestedNamespace' => $this->suggestedNamespace($class, $token, $expectedPatterns),
                ];
            }
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
                'supportingMembers' => $supportingMembers,
                'entrypoints' => $entrypoints,
                'roles' => array_combine(
                    $memberNames,
                    array_map(fn(string $className): string => $roles[$className], $memberNames)
                ),
                'confidence' => $confidence,
                'evidence' => [
                    'token' => $token,
                    'signals' => $signals,
                    'supporting' => array_map(
                        fn(string $className): array => [
                            'class' => $className,
                            'role' => $roles[$className],
                            'reason' => 'name_or_location_match',
                        ],
                        $supportingMembers
                    ),
                ],
                'namespace' => [
                    'expectedPatterns' => $expectedPatterns,
                    'alignedMembers' => $alignedMembers,
                    'driftingMembers' => $driftingMembers,
                ],
            ];
        }

        $this->addRelatedMembers($candidates, $architecture, $roles);
        $memberships = $this->buildMemberships($architecture, $candidates, $roles);

        usort($candidates, static fn(array $left, array $right): int => strcmp($left['id'], $right['id']));
        ksort($memberships);
        $architecture->setClassMemberships($memberships);
        $unassigned = array_keys(array_filter(
            $memberships,
            static fn(array $membership): bool => $membership['primaryCandidate'] === null
                && $membership['relatedCandidates'] === []
                && $membership['supportingCandidates'] === []
        ));
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
        return array_values(array_unique(array_map(
            fn(string $part): string => $this->canonicalToken(strtolower($part)),
            $parts
        )));
    }

    private function canonicalToken(string $token): string
    {
        if (strlen($token) > 3 && str_ends_with($token, 'ies')) {
            return substr($token, 0, -3) . 'y';
        }
        if (strlen($token) > 3 && str_ends_with($token, 's') && !str_ends_with($token, 'ss')) {
            return substr($token, 0, -1);
        }
        return $token;
    }

    private function classifyRole(ClassEntity $class): string
    {
        $tokens = array_merge(
            $this->normalizeTokens($class->getName()),
            $this->normalizeTokens($class->getNamespace()),
            $this->normalizeTokens($class->getFile())
        );
        $tokens = array_fill_keys($tokens, true);
        $name = strtolower($class->getName());

        if (isset($tokens['test']) || isset($tokens['tests']) || str_ends_with($name, 'test')) {
            return 'test';
        }
        if (isset($tokens['migration']) || isset($tokens['migrations'])) {
            return 'migration';
        }
        if ($name === 'kernel' || $name === 'bootstrap' || isset($tokens['framework'])) {
            return 'framework';
        }
        if (isset($tokens['shared']) || isset($tokens['common']) || isset($tokens['utility']) || isset($tokens['helper'])) {
            return 'shared';
        }
        if (isset($tokens['infrastructure']) || isset($tokens['infra'])) {
            return 'infrastructure';
        }
        if (isset($tokens['controller']) || isset($tokens['http']) || str_ends_with($name, 'controller')) {
            return 'interface';
        }
        if (isset($tokens['persistence']) || isset($tokens['repository']) || isset($tokens['table'])) {
            return 'persistence';
        }
        if (isset($tokens['application']) || isset($tokens['command']) || str_ends_with($name, 'service')) {
            return 'application';
        }
        if (isset($tokens['domain']) || isset($tokens['entity']) || $class->getNamespace() !== '') {
            return 'domain';
        }
        return 'unknown';
    }

    private function isCoreEligible(string $role): bool
    {
        return !in_array($role, ['test', 'migration', 'framework'], true);
    }

    /**
     * @param string[] $patterns
     */
    private function matchesNamespacePattern(string $namespace, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $quoted = preg_quote(trim($pattern, '\\'), '/');
            $regex = '/^' . str_replace('\\*', '.*', $quoted) . '(?:\\\\|$)/';
            if (preg_match($regex, trim($namespace, '\\')) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string[] $patterns
     */
    private function suggestedNamespace(ClassEntity $class, string $token, array $patterns): string
    {
        if (isset($this->namespacePatterns[$token])) {
            return trim($patterns[0], '\\');
        }

        $segments = array_values(array_filter(explode('\\', $class->getNamespace())));
        $root = $segments[0] ?? '';
        return $root === '' ? ucfirst($token) : $root . '\\' . ucfirst($token);
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, string> $roles
     */
    private function addRelatedMembers(array &$candidates, Architecture $architecture, array $roles): void
    {
        foreach ($candidates as &$candidate) {
            $core = array_fill_keys($candidate['coreMembers'], true);
            $related = [];
            foreach ($architecture->getDependencies() as $dependency) {
                $from = $dependency->getFrom()->getFullyQualifiedName();
                $to = $dependency->getTo()->getFullyQualifiedName();
                if (isset($core[$from]) && isset($roles[$to]) && !isset($core[$to]) && $this->isCoreEligible($roles[$to])) {
                    $related[$to] = true;
                }
                if (isset($core[$to]) && isset($roles[$from]) && !isset($core[$from]) && $this->isCoreEligible($roles[$from])) {
                    $related[$from] = true;
                }
            }
            $candidate['relatedMembers'] = array_keys($related);
            sort($candidate['relatedMembers']);
        }
        unset($candidate);
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, string> $roles
     * @return array<string, array<string, mixed>>
     */
    private function buildMemberships(Architecture $architecture, array $candidates, array $roles): array
    {
        $memberships = [];
        foreach ($architecture->getClasses() as $class) {
            $className = $class->getFullyQualifiedName();
            $coreCandidates = [];
            $relatedCandidates = [];
            $supportingCandidates = [];
            foreach ($candidates as $candidate) {
                if (in_array($className, $candidate['coreMembers'], true)) {
                    $coreCandidates[] = $candidate['id'];
                }
                if (in_array($className, $candidate['relatedMembers'], true)) {
                    $relatedCandidates[] = $candidate['id'];
                }
                if (in_array($className, $candidate['supportingMembers'], true)) {
                    $supportingCandidates[] = $candidate['id'];
                }
            }
            sort($coreCandidates);
            sort($relatedCandidates);
            sort($supportingCandidates);
            $memberships[$className] = [
                'role' => $roles[$className],
                'primaryCandidate' => $coreCandidates[0] ?? null,
                'relatedCandidates' => $relatedCandidates,
                'supportingCandidates' => $supportingCandidates,
            ];
        }
        return $memberships;
    }

}