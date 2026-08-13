Status: ready-for-agent

# Architecture Discovery CLI for PHP and CakePHP Projects

## Problem Statement

Teams working with large PHP applications, especially legacy CakePHP codebases, need a reliable way to understand the shape of their application architecture without guessing from folder structure alone. Existing conventions such as Controller/, Model/Table/, and Model/Entity/ can be misleading because they reflect framework structure rather than actual business boundaries. The team needs a tool that can statically analyze a project, reveal the class and dependency landscape, and produce a versioned architecture model that can be reviewed, compared, and later extended with clustering or LLM-based suggestions.

The current challenge is not only finding classes and dependencies, but also recognizing framework-specific relationships like CakePHP ORM associations, dynamic table resolution, service composition, and domain-relevant coupling that are not obvious from naming conventions or directory layouts. Without a deterministic and reproducible analysis layer, any architectural interpretation remains speculative and hard to validate.

The project needs a CLI tool that can accept a project path, parse PHP files into a normalized intermediate representation, identify dependencies and CakePHP-specific relationships, and emit a stable architecture artifact. This foundation must be independent of LLM usage so that architecture insights remain repeatable, reviewable, and machine-comparable.

A first-class requirement is that project development and execution happen in a Docker container so contributors do not need to install PHP or framework tooling locally. This reduces setup friction, ensures consistent runtime behavior across machines, and makes the project easier to onboard into CI and shared environments.

## Solution

Build a deterministic architecture discovery CLI that analyses a PHP project with a focus on CakePHP applications, extracts class-level, dependency-level, and framework relationship data, and writes a versioned architecture model to architecture.json. The tool should prioritize statically recognized relationships, explicit framework conventions, and reproducible graph generation over guessing or hidden heuristics.

The solution is a modular pipeline: project scanning, PHP AST extraction, dependency graph creation, CakePHP-specific analysis, metrics and clustering, and report generation. The output architecture model becomes the stable contract for downstream consumers such as visualizations, reports, manual module configuration, and future LLM-based context suggestions. The system does not make architecture decisions for the user; it detects relationships, suggests likely clusters, and leaves final confirmation to human review.

To keep the project adaptable and easy to extend, the implementation will follow a hexagonal architecture with concrete integrations hidden behind interfaces. The core analysis domain will depend on abstractions for source discovery, static analysis engines, graph generation, rendering adapters, and output providers. This allows the project to swap a parser or graph rendering library without rewriting the core logic. Docker will provide the execution environment for the tool, while the codebase itself remains open to alternative implementations behind those interfaces.

## User Stories

1. As a software architect, I want to run a CLI against a PHP project, so that I can obtain a reproducible architecture overview without manually reading the codebase.
2. As a contributor setting up the project, I want the toolchain to run in Docker, so that I do not need to install PHP locally and the environment is consistent across machines.
3. As a legacy application maintainer, I want the tool to analyze PHP files recursively, so that I can see classes, interfaces, traits, and namespaces even in a large codebase.
4. As a CakePHP team lead, I want ORM associations such as belongsTo, hasMany, hasOne, and belongsToMany to be recognized, so that I can map actual domain relationships instead of just filesystem layout.
5. As an engineer working with dynamic framework calls, I want fetchTable() and loadModel() dependencies to be interpreted where statically possible, so that I can catch hidden coupling in the application.
6. As a technical reviewer, I want dependencies to be typed and weighted, so that I can distinguish between core framework usage, strong domain coupling, and low-value import references.
7. As a project owner, I want a stable architecture.json artifact, so that analysis results can be saved, compared over time, and used as input for visualizations and later review.
8. As a developer, I want a Graphviz/SVG output, so that I can inspect the codebase as a dependency graph and spot structural hotspots.
9. As a developer exploring multiple visualization strategies, I want renderers to be swappable behind interfaces, so that I can experiment with Graphviz, Mermaid, or other output formats without changing the core analysis flow.
10. As a team doing modernization work, I want metrics such as incoming/outgoing dependency counts and cohesion approximations, so that I can identify strong or weak module boundaries.
11. As an architecture reviewer, I want clusters or module candidates to be generated from dependency structure, so that I can explore likely groupings without forcing a hard-coded module layout.
12. As a human reviewer, I want manual module definitions to be allowed, so that I can override or refine algorithmic cluster suggestions with domain knowledge.
13. As a future LLM integration user, I want the LLM to receive only a normalized intermediate representation and relevant summaries, so that it helps with naming and context suggestions without consuming the full repository blindly.
14. As a team working with sensitive enterprise code, I want LLM support to remain optional and explicitly separated from the core analysis pipeline, so that confidential source code is not sent to a provider unless the user opts in.
15. As a release manager, I want the tool to support a report output and a CLI mode focused on analysis, so that it fits into an engineering workflow and documentation process.
16. As a maintainer of an evolving codebase, I want the analysis to be versionable and deterministic, so that architecture trends can be tracked across time and reviewed by stakeholders.
17. As a developer writing tests, I want fixtures that cover simple PHP and CakePHP patterns, so that parser behavior and dependency extraction stay trustworthy over time.
18. As a contributor to the project, I want the architecture tool to be modular and hexagonally structured, so that parser logic, graph logic, and reporting can evolve independently.
19. As a maintainer choosing analysis libraries, I want parser and analyzer implementations to be replaceable behind interfaces, so that I can swap static analysis backends or adapters without changing core business logic.
20. As a team using the tool in CI or review workflows, I want the architecture model to act as the main contract, so that other tools can consume it without depending on one presentation format.
21. As a user of the architecture tool, I want a simple CLI contract for analyse, graph, and report operations, so that I can run the tool consistently across multiple environments.

## Implementation Decisions

- The project will be implemented as a PHP CLI application centered on a deterministic static analysis pipeline. The core entry point is the command layer, which accepts a directory path and delegates to a project scanner and analysis pipeline.
- The project will be developed and executed in Docker so that contributors do not need to install PHP locally. Docker will provide a consistent runtime, dependency installation flow, and test environment across developer machines and CI.
- The primary domain model will be an explicit architecture intermediate representation, serialized to architecture.json. This model is the canonical artifact for all downstream consumers, including graph generation, reports, clustering, and LLM use.
- The codebase will follow hexagonal architecture principles: the core domain will define interfaces for project scanning, static analysis, graph generation, rendering, and output generation, while concrete implementations remain behind adapters. This makes the architecture easier to extend and swap without disturbing the analysis domain.
- The architecture should support replaceable implementations for more than just parsing and rendering. The project should explicitly hide concrete implementations behind interfaces for file discovery, project source selection, dependency resolution, framework-specific analyzers, metric calculation, clustering algorithms, serializer/storage, report generation, cache backends, and optional LLM providers.
- The project will prioritize static AST analysis using PHP parser capabilities instead of runtime reflection or ad hoc regex scanning. This keeps behavior deterministic and auditable across repositories.
- The system will treat PHP classes, interfaces, traits, namespaces, inheritance, and implementation relationships as first-class entities in the analysis model. Dependency edges will be captured with a typed relationship and weighting.
- The dependency graph will be directed and weighted. Initial weights will distinguish framework usage, direct object coupling, ORM relations, and code-level type usage so the graph is useful for both visual inspection and module suggestion.
- CakePHP-specific logic will be implemented as a dedicated analysis layer rather than spread across generic PHP parsing. This preserves the separation between framework-aware interpretation and generic PHP extraction.
- Framework classes such as Cake\* and PSR interfaces will be treated as framework dependencies and filtered or downgraded during clustering and module analysis so they do not falsely dominate the architecture picture.
- The analysis pipeline will divide into clear high-level seams: project scanning, AST node extraction, dependency building, CakePHP analysis, metrics, clustering, and reporting. These seams are intentionally kept high-level to avoid unnecessary cross-cutting complexity.
- The project will produce at least three output types: machine-readable architecture.json, visualization output such as Graphviz dot/SVG, and a static HTML report. These outputs should be derived from the same underlying model rather than each storing a separate representation.
- Visualization providers will be implemented behind a renderer interface so the system can support Graphviz, Mermaid, and other approaches later without rewriting the analysis pipeline or report generation contract.
- Source discovery and file iteration will also be abstracted, allowing alternate strategies for scanning filesystem trees, filtering vendor code, or reading from a cached artifact instead of live files.
- Dependency graph building and edge weighting will be encapsulated behind a graph engine interface so the project can compare different structural heuristics, weights, or graph libraries without changing the application model.
- Metrics and clustering algorithms will be treated as pluggable analyzers. This includes simple connected-component approaches, community detection, and later alternatives such as Leiden or other graph-based strategies.
- Report generation and artifact serialization will be interface-driven so HTML, JSON, Markdown, and other formats can be produced from the same architecture model without tying the domain to one output implementation.
- Persistence and caching will be abstracted as storage adapters, allowing local filesystem caching, memory-backed caching, or remote artifact storage in future iterations.
- Manual module definitions will be supported as an override layer. Users may assign classes or files to named module groups, and these definitions will be kept distinct from automatically detected clusters.
- LLM integration will be deferred behind the architecture model boundary; the LLM receives normalized input such as architecture summaries, clusters, dependencies, and class names, not raw repository content.
- The tool must not make or assume final architecture decisions. It should explicitly separate detected facts from suggested clusters and confirmed human-reviewed contexts.
- The schema for the architecture model must be versioned and backward-compatible enough to support future evolution without breaking analysis consumers.
- Static analysis providers, rendering adapters, and storage/serialization components will all be designed as replaceable implementations behind interfaces so the tool can evolve without coupled dependencies.
- A cache layer for file hashes and parsed AST content is planned for future scalability, but the initial implementation should focus on a correct, reproducible baseline.
- The project should be structured to allow future integration with PHPat or CI-based architecture checks, but those features intentionally remain outside the initial version.

## Testing Decisions

- The team should prefer fixture-based tests that assert on external behavior: the produced architecture JSON and dependency graph should be checked as observable outputs rather than internal parser implementation details.
- A first-class development requirement is Docker-based test execution. The project should include a standard containerized workflow for running tests, linting, and local CLI execution so no developer needs a host PHP installation.
- Unit tests will cover the parser inputs for class detection, interfaces, traits, inheritance, namespace handling, type hints, method calls, and dependency extraction.
- CakePHP-specific tests will cover belongsTo, hasMany, hasOne, belongsToMany, fetchTable, and loadModel behavior using targeted fixture projects that reflect common real-world patterns.
- Integration tests will run the CLI on fixture directories and validate that generated architecture.json contains the expected class and dependency information.
- The project should use a small set of representative fixture apps with expected architecture fragments, including a simple PHP project, a basic CakePHP app, a legacy CakePHP app, a hexagonal-style CakePHP app, and a mixed architecture example.
- Testing should verify reproducibility: the same project input should generate the same architecture output in a stable manner.
- The tool’s tests should focus on the public outputs rather than the AST implementation details, which can change as the parser evolves without altering the external contract.
- The project should also test the extension seams explicitly: swapping a static analysis provider, swapping a renderer adapter, swapping a clustering algorithm, and confirming that the core domain remains stable despite implementation changes.
- Tests should also validate that the architecture abstractions stay stable when alternative providers are plugged in, especially for file discovery, graph building, metric calculation, serialization, and optional LLM integration.
- Previous patterns in architecture analysis tools suggest that fixture-driven expectation tests are the highest-value test seam, because they exercise the full pipeline from files to generated model and avoid brittle unit tests around every internal node.

## Out of Scope

- Automatic architecture decision-making or forced bounded-context naming without human review.
- Full LLM integration in the initial version. LLM support is a later extension.
- PHPat rule generation, architecture enforcement, and CI gating in the initial scope.
- Real-time or interactive graph exploration beyond static output generation.
- Multi-language support beyond the initial PHP-first scope.
- Perfect semantic understanding of every dynamic PHP pattern. The tool should be explicit about static-only detection and not overstate certainty.
- A complete optimization layer for extremely large monorepos in the initial milestone.

## Further Notes

This feature is intentionally scoped around a strong foundation: a deterministic architecture model and static detection pipeline. Once that model is stable, the rest of the product can evolve by adding graph rendering, metrics, clustering, manual overrides, and optional LLM suggestions without changing the core contract.

The project will also prioritize a low-friction contributor environment. Development should happen inside Docker so the runtime, PHP version, composer dependencies, and CLI workflows are consistent. This keeps onboarding simple and avoids local installation drift.

The key principle is that architecture.json is the authoritative artifact. All downstream outputs should be consumers of this model rather than source-of-truth alternatives. This keeps the project modular and makes the architecture information reproducible, reviewable, and adaptable as requirements evolve.

The supporting design principle is that the boundaries are intentionally replaceable: parser engines, graph renderers, and output adapters are all treated as implementation choices behind interfaces rather than built into the domain logic. This enables future experimentation with alternative static analysis backends and visualization formats while preserving the stable architecture pipeline.
