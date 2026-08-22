# 06 — Optional LLM context suggestions

**What to build:** An opt-in LLM flow that receives only normalized architecture data and structured summaries, and returns context suggestions without depending on raw repository access.

**Blocked by:** 05 — Metrics, clustering, and generated artifacts

**Status:** ready-for-human

- [x] Acceptance criterion: the LLM path is explicitly optional and separate from the core analysis pipeline
- [x] Acceptance criterion: the LLM receives the architecture model and summaries instead of raw source code
- [x] Acceptance criterion: suggestions are returned in a structured format and clearly separate detected facts from suggested interpretations

## Implementation Notes

- Added `LlmProviderInterface` as an explicit port for local LLM adapters. No provider is instantiated by the core analysis command.
- Added `ArchitectureContextBuilder`, which sends only model version, project summary, class identities, dependency edges, metrics, and clusters; source paths and raw source are excluded.
- Added `ContextSuggestionService` to invoke an injected provider and return `detectedFacts` separately from `suggestedInterpretations`.
- Provider injection keeps LLM usage opt-in and preserves the local-only policy. External providers are not bundled.

## Validation

- PHPUnit: 39 tests and 142 assertions passing.
- Provider-boundary coverage verifies normalized input filtering and structured fact/interpretation output.
