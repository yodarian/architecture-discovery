<?php
namespace ArchitectureDiscovery\Domain\Model;

use DateTimeInterface;

/**
 * ProjectMetadata represents the metadata about a scanned PHP project.
 */
final class ProjectMetadata
{
    private string $name;
    private string $path;
    private string $version;
    private DateTimeInterface $generatedAt;
    /** @var array<string, mixed> */
    private array $composerData = [];

    /**
     * @param array<string, mixed> $composerData Optional Composer data from composer.json
     */
    public function __construct(
        string $name,
        string $path,
        string $version,
        DateTimeInterface $generatedAt,
        array $composerData = []
    ) {
        $this->name = $name;
        $this->path = $path;
        $this->version = $version;
        $this->generatedAt = $generatedAt;
        $this->composerData = $composerData;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getGeneratedAt(): DateTimeInterface
    {
        return $this->generatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getComposerData(): array
    {
        return $this->composerData;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'version' => $this->version,
            'composer' => !empty($this->composerData) ? $this->composerData : null,
        ];
    }
}
