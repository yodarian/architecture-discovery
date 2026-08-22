<?php
namespace ArchitectureDiscovery\Domain\Model;

/**
 * Dependency represents a directed edge between two classes.
 * Dependencies are typed to distinguish framework usage, domain coupling, ORM relations, etc.
 */
final class Dependency
{
    // Dependency types
    public const TYPE_USES = 'uses';                   // direct object dependency
    public const TYPE_IMPLEMENTS = 'implements';       // interface implementation
    public const TYPE_EXTENDS = 'extends';             // class inheritance
    public const TYPE_TRAIT_USE = 'trait_use';        // trait usage
    public const TYPE_METHOD_CALL = 'method_call';    // method call
    public const TYPE_PROPERTY_TYPE = 'property_type'; // property type hint
    public const TYPE_RETURN_TYPE = 'return_type';    // return type
    public const TYPE_PARAMETER_TYPE = 'parameter_type'; // parameter type hint
    public const TYPE_ORM_RELATION = 'orm_relation';  // ORM association (CakePHP)
    public const TYPE_DYNAMIC_CALL = 'dynamic_call';  // dynamic call like fetchTable/loadModel

    private ClassEntity $from;
    private ClassEntity $to;
    private string $type;
    private int $weight;
    /** @var array<string, mixed> */
    private array $metadata = [];

    /**
     * @param array<string, mixed> $metadata Optional metadata about the dependency
     */
    public function __construct(
        ClassEntity $from,
        ClassEntity $to,
        string $type,
        int $weight = 1,
        array $metadata = []
    ) {
        $this->from = $from;
        $this->to = $to;
        $this->type = $type;
        $this->weight = $weight;
        $this->metadata = $metadata;
    }

    public function getFrom(): ClassEntity
    {
        return $this->from;
    }

    public function getTo(): ClassEntity
    {
        return $this->to;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'from' => $this->from->getFullyQualifiedName(),
            'to' => $this->to->getFullyQualifiedName(),
            'type' => $this->type,
            'weight' => $this->weight,
        ];

        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}
