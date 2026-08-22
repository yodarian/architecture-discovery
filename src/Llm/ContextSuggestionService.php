<?php
namespace ArchitectureDiscovery\Llm;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Coordinates the opt-in suggestion flow without coupling core analysis to an LLM.
 */
final class ContextSuggestionService
{
    public function __construct(private ArchitectureContextBuilder $contextBuilder)
    {
    }

    /**
     * @return array{provider: string, detectedFacts: array<string, mixed>, suggestedInterpretations: array<int, array<string, mixed>>}
     */
    public function suggest(Architecture $architecture, LlmProviderInterface $provider): array
    {
        $context = $this->contextBuilder->build($architecture);
        return [
            'provider' => $provider->name(),
            'detectedFacts' => $context,
            'suggestedInterpretations' => $provider->suggest($context),
        ];
    }
}
