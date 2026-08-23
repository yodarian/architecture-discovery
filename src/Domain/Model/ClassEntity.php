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
    /** @var string[] */
    private array $typeDependencies = [];
    /** @var string[] */
    private array $propertyTypeDependencies = [];
    /** @var string[] */
    private array $parameterTypeDependencies = [];
    /** @var string[] */
    private array $returnTypeDependencies = [];
    /** @var string[] */
    private array $staticCallDependencies = [];

    /**
     * @param string[] $interfaces Fully qualified names of implemented interfaces
     * @param string[] $traits Fully qualified names of used traits
     * @param string[] $typeDependencies Every type name referenced anywhere in the class body
     * @param string[] $propertyTypeDependencies Property type hints, including promoted constructor params
     * @param string[] $parameterTypeDependencies Non-promoted method parameter type hints
     * @param string[] $returnTypeDependencies Method return type hints
     * @param string[] $staticCallDependencies Classes referenced via a static method call (Foo::bar())
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
        bool $isAbstract = false,
        array $typeDependencies = [],
        array $propertyTypeDependencies = [],
        array $parameterTypeDependencies = [],
        array $returnTypeDependencies = [],
        array $staticCallDependencies = []
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
        $this->typeDependencies = array_values(array_unique($typeDependencies));
        $this->propertyTypeDependencies = array_values(array_unique($propertyTypeDependencies));
        $this->parameterTypeDependencies = array_values(array_unique($parameterTypeDependencies));
        $this->returnTypeDependencies = array_values(array_unique($returnTypeDependencies));
        $this->staticCallDependencies = array_values(array_unique($staticCallDependencies));
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
     * @return string[]
     */
    public function getTypeDependencies(): array
    {
        return $this->typeDependencies;
    }

    /**
     * @return string[]
     */
    public function getPropertyTypeDependencies(): array
    {
        return $this->propertyTypeDependencies;
    }

    /**
     * @return string[]
     */
    public function getParameterTypeDependencies(): array
    {
        return $this->parameterTypeDependencies;
    }

    /**
     * @return string[]
     */
    public function getReturnTypeDependencies(): array
    {
        return $this->returnTypeDependencies;
    }

    /**
     * @return string[]
     */
    public function getStaticCallDependencies(): array
    {
        return $this->staticCallDependencies;
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

        if (!empty($this->typeDependencies)) {
            $data['typeDependencies'] = $this->typeDependencies;
        }

        return $data;
    }
}
