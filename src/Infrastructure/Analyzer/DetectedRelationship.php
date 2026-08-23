<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

/**
 * A single relationship a FrameworkPatternDetector found in one file, before
 * it's been bound to a discovered class in the Architecture model.
 */
final class DetectedRelationship
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $from,
        public readonly ?string $target,
        public readonly string $type,
        public readonly int $weight,
        public readonly ?Framework $framework = null,
        public readonly array $metadata = []
    ) {
    }

    public function withFramework(Framework $framework): self
    {
        return new self($this->from, $this->target, $this->type, $this->weight, $framework, $this->metadata);
    }
}
