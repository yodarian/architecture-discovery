# CONTEXT — Ubiquitous Language

This file captures the working glossary and core decisions for the Architecture Discovery Tool project. It is intentionally concise and implementation-agnostic.

## Canonical Terminology

- **Cluster**: A graph-theoretical grouping of strongly related code elements produced by the clustering algorithm.
- **Module Candidate**: A Cluster interpreted as a potentially useful architectural unit produced by the Analyzer + heuristics.
- **Module Candidate Membership**: A class may be a core member of one candidate, a related collaborator of multiple candidates, or unassigned when evidence is insufficient. Membership is a hypothesis, not ownership.
- **Module Candidate Owner**: The candidate that most strongly explains a class's naming, location, and structural role. A class may have no owner when evidence is ambiguous.
- **Namespace Alignment**: Whether a module candidate member is located under the candidate's expected namespace or configured namespace pattern. Membership and namespace alignment are separate facts.
- **Namespace Drift**: A module candidate member whose naming, path, or dependency evidence associates it with a candidate while its current namespace is outside the candidate's expected namespace.
- **Module**: An explicitly defined architectural code boundary created and maintained by the Developer.
- **Bounded Context Candidate**: A hypothesis that one or more Modules represent a distinct DDD bounded context, suggested by LLM + analysis.
- **Bounded Context**: A confirmed DDD boundary with an explicit domain meaning and model boundary, agreed and produced by Developer/domain experts.
- **Module Overview Diagram**: A human-facing visualization of module candidates and aggregated dependencies between them. It is distinct from the raw class dependency graph.
- **Module Details Diagram**: A class-level visualization used to inspect the evidence behind a module candidate, including core members, related collaborators, and candidate-adjacent unassigned classes.
- **Architecture Map**: A generated Markdown summary (`architecture-map.md`) of the architecture model. It begins with a project-wide namespace tree showing cumulative class counts, followed by cluster summaries that include up to five member classes ranked by incoming dependency count, total degree, and fully-qualified name, alongside cluster metrics and framework-tagged relation counts. It is produced so an agent or human can orient in an unfamiliar codebase without reading `architecture.json` or the source in full. It contains structural facts, not ubiquitous-language content; domain terminology stays the responsibility of a project's own `CONTEXT.md`.

## Key Domain Concepts (examples used in discussions)

- `Order`, `OrderItem`, `Customer`, `Product`, `Invoice`, `Billing`, `Shipment`

## Decisions and Policies

- Static analysis is the canonical source for the initial dependency graph; `architecture.json` is the central, versioned IR.
- Framework/library nodes (e.g. `Cake\*`, `Psr\*`, `PHPUnit\*`) are excluded from clustering and treated as infra nodes.
- For dynamic resolvers like `fetchTable('X')` and `loadModel('Y')`, heuristics should attempt name resolution and mark edges as "dynamic" when uncertain.
- ORM/framework relationship detection is pluggable per framework (CakePHP, Laravel Eloquent). Which detector(s) run is gated by `composer.json`'s `require` (e.g. `cakephp/cakephp`, `laravel/framework`) when available, falling back to running all detectors when `composer.json` is absent or inconclusive — this avoids false-positive edges from same-named methods (e.g. `belongsTo`) across unrelated frameworks. Detected edges carry a `framework` metadata tag but reuse the same generic dependency types (`orm_relation`, `dynamic_call`) regardless of source framework.
- Classes named `*Service` are treated as potentially domain services or helpers; the Analyzer preserves the name and context for human review.
- Clustering granularity: feature/module level (cluster contains classes belonging to a feature/module).
- Clusters are technical groupings; they may become Module Candidates after analysis and human review.
- Module candidates are reported separately from raw clusters and may include classes scattered across namespaces or directories.
- Module candidate discovery supports both inferred vocabulary from code and explicit project-provided terms or aliases.
- Tests and migrations may be excluded from module membership, while controllers are included as feature entrypoints. Framework adapters and shared collaborators remain reportable as related code rather than being silently assigned to every feature.
- Static analysis may report shared module candidates, related candidates, and unassigned classes; it must not force every class into exactly one module.
- A class may have one primary candidate owner, multiple related candidates, or no owner. A strong shared concept may receive its own candidate.
- Module detail views report core members, related/shared collaborators, candidate-adjacent unassigned classes, roles/layers, and namespace alignment. They may suggest a target location but never perform an automatic move.
- Visualizations provide a legend. Target abstraction type is encoded by edge color, dependency meaning by edge style or label, and candidate confidence by both visual treatment and explicit text.
- The raw class graph and module overview are separate visualizations. The module overview aggregates cross-candidate dependencies and shows shared candidates once rather than duplicating them.
- The tool never writes into the analyzed project. `analyse` defaults to writing all artifacts (`architecture.json`, `graph.svg`, `report.html`, `architecture-map.md`) to `out/<project-name>/` inside this tool's own repo; `--output` may override the location but the analyzed project itself is never a valid implicit target.
- Authoring a `CONTEXT.md` glossary for an analyzed project is a manual or Agent-Skill task, not something this tool generates. The tool used to template a `CONTEXT.md` into analyzed projects (`app:bootstrap-context`); that conflated glossary authoring with static analysis and was removed.

## Ownership Example (illustrative)

Given an `Order` that references `Customer`:

- Scenario A — `Customer` is part of the Order context: If `Customer` exists only to manage orders (small B2B app), then the Order context may own `Customer` (including `CustomerId`, `Name`, `BillingAddress`, `ShippingAddress`).
- The ownership decision is architectural and must be confirmed by domain experts — the tool only suggests hypotheses.

## LLM Policy

- The core tool produces `architecture.json` which is LLM-agnostic.
- LLM usage is optional. Only local LLMs are allowed by policy — no external/online LLM providers will be used unless explicitly permitted.

## Metrics (prioritized)

- Primary: external coupling, outgoing dependencies, class count. These are configurable and intended to be easy to adjust.

## Agreement on IR and MVP

- `architecture.json` remains the central, versioned artifact exchanged between analyzers, visualizers, clustering, and optional LLMs.
- The MVP definition in the development plan will be used as the starting target; it can be refined during implementation.

## Next Steps

- The Analyzer will tag infra nodes and dynamic edges according to the policies above.

---
Generated by the Architecture Discovery pairing session on 2026-08-12.
