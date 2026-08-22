<?php
namespace ArchitectureDiscovery\Llm;

/**
 * Port for an optional local LLM provider.
 * Providers receive normalized architecture data, never repository source.
 */
interface LlmProviderInterface
{
    public function name(): string;

    /**
     * @param array<string, mixed> $normalizedContext
     * @return array<int, array<string, mixed>>
     */
    public function suggest(array $normalizedContext): array;
}
