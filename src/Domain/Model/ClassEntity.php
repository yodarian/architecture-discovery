<?php
namespace ArchitectureDiscovery\Domain\Model;

/**
 * ClassEntity represents a PHP class, interface, or trait.
 */
final class ClassEntity
{
    public const TYPE_CLASS = 'class';
    public const TYPE_INTERFACE = 'interface';
    public const TYPE_TRAIT = 'trait';

    private string $fullyQualifiedName;
    private string $type;
    private string $namespace;
    private string $name;
    private string $file;
    private int $line;
    /** @var string[] */
    private array $interfaces = [];
    /** @var string[] */
    private array $traits = [];
    private ?string $extends = null;
    private bool $isAbstract;

    /**
     * @param string[] $interfaces Fully qualified names of implemented interfaces
     * @param string[] $traits Fully qualified names of used traits
     */
    public function __construct(
        string $fullyQualifiedName,
        string $type,
        string $namespace,
        string $name,
        string $file,
        int $line,
        array $interfaces = [],
        array $traits = [],
        ?string $extends = null,
        bool $isAbstract = false
    ) {
        $this->fullyQualifiedName = $fullyQualifiedName;
        $this->type = $type;
        $this->namespace = $namespace;
        $this->name = $name;
        $this->file = $file;
        $this->line = $line;
        $this->interfaces = $interfaces;
        $this->traits = $traits;
        $this->extends = $extends;
        $this->isAbstract = $isAbstract;
    }

    public function getFullyQualifiedName(): string
    {
        return $this->fullyQualifiedName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return string[]
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * @return string[]
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    public function getExtends(): ?string
    {
        return $this->extends;
    }

    public function isAbstract(): bool
    {
        return $this->isAbstract;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'fqn' => $this->fullyQualifiedName,
            'type' => $this->type,
            'namespace' => $this->namespace,
            'name' => $this->name,
            'file' => $this->file,
            'line' => $this->line,
            'abstract' => $this->isAbstract,
        ];

        if (!empty($this->interfaces)) {
            $data['implements'] = $this->interfaces;
        }

        if (!empty($this->traits)) {
            $data['uses'] = $this->traits;
        }

        if ($this->extends !== null) {
            $data['extends'] = $this->extends;
        }

        return $data;
    }
}
