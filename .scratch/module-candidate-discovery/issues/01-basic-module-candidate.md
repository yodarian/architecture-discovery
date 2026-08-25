# 01 — Discover a Basic Module Candidate

**What to build:** The analyzer recognizes a feature such as Todo from normalized class names, namespaces, and paths, produces a named module candidate with deterministic confidence and evidence, and includes it in `architecture.json` while preserving raw clusters.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Normalize PascalCase, camelCase, snake_case, kebab-case, and namespace segments into boundary-aware tokens.
- [ ] Infer a Todo candidate from the small example project without project-specific configuration.
- [ ] Include Todo-related classes across application, domain, interface, infrastructure, and persistence roles.
- [ ] Include feature controllers as candidate entrypoints.
- [ ] Keep raw graph clusters separate from module candidates.
- [ ] Serialize candidate identity, membership, confidence, and structured evidence in `architecture.json`.
- [ ] Preserve compatibility for consumers that only read existing architecture fields.
- [ ] Add focused tests for deterministic token inference, candidate membership, evidence, and output serialization.
