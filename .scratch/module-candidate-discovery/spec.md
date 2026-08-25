Status: ready-for-agent

# Discover Explainable Module Candidates and Visualize Them

## Problem Statement

The architecture discovery tool currently produces a class dependency graph and raw connected-component clusters. Those clusters are useful graph facts, but they are too coarse to help a human developer identify probable feature modules in a large or partially organized PHP project.

A real project may contain classes belonging to one feature across several namespaces and directories, shared classes used by multiple features, legacy classes that have not yet moved into the expected namespace, tests and migrations that should not define module membership, and unrelated code that cannot be classified confidently. The current connected-component approach cannot represent those distinctions. It can merge unrelated areas through a shared bootstrap or infrastructure class, and it treats isolated classes as highly cohesive clusters despite having no supporting evidence.

The existing Graphviz output is also class-level only and uses one default visual style for every node and dependency. A human developer needs a module-level overview for orientation and a detailed view that explains why a class was associated with a candidate. The visual output must make interfaces, concrete classes, framework relationships, confidence, shared collaborators, unassigned code, and namespace drift understandable through a legend.

Static analysis should provide reproducible, explainable module hypotheses. It must not claim that a candidate is a confirmed module or bounded context, and it must not require the postponed LLM integration.

## Solution

Extend the static analysis pipeline with a deterministic module-candidate discovery layer. Keep raw dependency clusters as graph-theoretical facts, and add a separate module-candidate interpretation to the canonical `architecture.json` artifact.

Module candidates are inferred from normalized name tokens, namespaces, paths, configured vocabulary and aliases, class roles, and dependency evidence. Configured terms are authoritative and receive stronger confidence; inferred terms allow discovery in unfamiliar projects with lower confidence. A candidate can include classes scattered across the codebase.

Represent class relationships to candidates explicitly. A class may be a core member of one candidate, a related collaborator of multiple candidates, a member of a strong shared candidate, or unassigned. Each membership includes confidence and structured evidence. Namespace alignment is reported independently from candidate membership, so a class can be recognized as Todo-related while visibly remaining in a legacy namespace.

Classify tests and migrations as supporting or excluded analysis material by default, include controllers as feature entrypoints, and prevent generic bootstrap/framework classes from defining ownership. Preserve shared infrastructure as a candidate or collaborator when evidence supports it instead of silently copying it into every feature.

Add a module-level Graphviz artifact, `modules.svg`, alongside the existing raw `graph.svg`. The module overview shows candidate nodes and aggregated dependencies. It displays shared candidates once, shows candidate-adjacent unassigned classes by default, and supports a mode that includes all unassigned classes. The raw graph remains available for class-level evidence and diagnostics.

Both visualizations include a legend. Edge color identifies the target abstraction type, while line style or labels identify the dependency meaning. Candidate confidence is shown using visual treatment and explicit text, and the output does not rely on color alone.

## User Stories

1. As a developer exploring an unfamiliar PHP project, I want the tool to identify probable feature/module candidates from static analysis, so that I can orient myself before reading the entire codebase.
2. As a developer, I want raw dependency clusters and module candidates reported separately, so that I can distinguish graph facts from architectural hypotheses.
3. As a developer working on a large legacy project, I want module candidates to include classes scattered across namespaces and directories, so that existing physical organization does not hide feature relationships.
4. As a developer, I want candidate names inferred from class names, namespace segments, and paths, so that useful candidates can be discovered without prior configuration.
5. As a developer, I want to configure domain terms and aliases, so that project-specific vocabulary such as `Task` and `WorkItem` can be recognized as related to `Todo` when I explicitly say they are equivalent.
6. As a developer, I want configured vocabulary to produce stronger evidence than inferred vocabulary, so that project knowledge influences the result without making the analysis non-deterministic.
7. As a developer, I want PascalCase, camelCase, snake_case, kebab-case, and namespace segments normalized into comparable tokens, so that equivalent naming styles are recognized consistently.
8. As a developer, I want token matching to be boundary-aware, so that an incidental substring does not incorrectly assign a class to a candidate.
9. As a developer, I want a class with a strong name or path match to be considered even when it has few dependency edges, so that legitimate peripheral or newly added feature classes are not missed.
10. As a developer, I want dependency evidence to expand a candidate beyond classes whose names contain the feature term, so that generic but feature-specific collaborators can be discovered.
11. As a developer, I want each candidate membership to include its reasons, so that I can review and challenge the analyzer's conclusion.
12. As a developer, I want each candidate membership to include a confidence score, so that I can focus review on ambiguous results.
13. As a developer, I want the evidence contributing to a score reported separately, so that the score is transparent and tunable rather than a mysterious decimal.
14. As a developer, I want classes that do not fit any candidate to remain explicitly unassigned, so that legacy and unexplained code is visible rather than forced into a false architecture.
15. As a developer, I want a class to be related to multiple candidates, so that collaboration is not confused with ownership.
16. As a developer, I want a class to have at most one primary candidate owner when the evidence supports one, so that ownership remains useful for review.
17. As a developer, I want a class to have no primary owner when evidence is ambiguous, so that the tool does not make an unjustified ownership decision.
18. As a developer, I want a widely reused concept such as `UserService` to be suggested as its own shared candidate when evidence is strong, so that it is not falsely shown as owned by every consuming feature.
19. As a developer, I want weakly shared utilities to remain outside candidate clusters while their collaborations are reported, so that generic infrastructure is not overinterpreted as a domain module.
20. As a developer, I want controllers included when they identify or depend on a feature candidate, so that module entrypoints are represented.
21. As a developer, I want generic bootstrap and framework controllers excluded from ownership inference, so that `AppController` or equivalent shared setup does not merge unrelated candidates.
22. As a developer, I want tests and migrations excluded from core module membership by default, so that supporting artifacts do not define business boundaries.
23. As a developer, I want tests and migrations retained as supporting evidence, so that I can trace how a candidate is exercised or persisted.
24. As a developer, I want class roles such as domain, application, interface, infrastructure, persistence, test, migration, framework, shared, and unknown reported, so that candidate composition is easier to interpret.
25. As a developer, I want namespace alignment reported separately from candidate membership, so that I can see whether a probable module is already physically organized.
26. As a developer, I want namespace drift highlighted, so that classes associated with a candidate but located in a legacy namespace are easy to find.
27. As a developer, I want configured namespace patterns to define alignment when a project uses a non-standard layout, so that the tool does not assume one namespace convention.
28. As a developer, I want the detail view to show a suggested target location for namespace-drifting classes, so that the result can guide future refactoring without moving files automatically.
29. As a developer, I want the tool to avoid automatic file or namespace changes, so that framework conventions, public APIs, autoloading, and persistence mappings remain under human control.
30. As a developer, I want a module overview diagram containing one node per candidate, so that I can see probable feature areas without a class-level hairball.
31. As a developer, I want dependencies between candidates aggregated into one edge with counts and weighted strength, so that the module overview remains readable on large projects.
32. As a developer, I want the detailed class graph available separately, so that I can inspect the concrete evidence behind an aggregated module edge.
33. As a developer, I want shared candidates displayed once and connected to their collaborators, so that the diagram does not imply duplicate ownership.
34. As a developer, I want candidate-adjacent unassigned classes shown by default, so that likely missing membership is visible during review.
35. As a developer, I want an option to show all unassigned classes, so that I can inspect the complete unexplained area when needed.
36. As a developer, I want dependency edges targeting interfaces shown in green, so that abstraction boundaries are visible quickly.
37. As a developer, I want dependency edges targeting concrete classes shown in red, so that direct implementation coupling is visible quickly.
38. As a developer, I want dependencies targeting abstract classes shown with a distinct amber treatment, so that they are not confused with interfaces or concrete implementations.
39. As a developer, I want framework and external dependencies shown in a subdued distinct style, so that framework coupling can be recognized without dominating the module overview.
40. As a developer, I want edge style or labels to communicate relationship types such as `implements`, `uses`, `parameter_type`, and `orm_relation`, so that color is not overloaded with multiple meanings.
41. As a developer, I want candidate confidence represented by both visual treatment and text, so that confidence remains understandable in accessibility and monochrome contexts.
42. As a developer, I want both diagrams to contain a legend, so that I can interpret colors, line styles, candidate borders, shared-candidate styling, and namespace-drift markers without external documentation.
43. As a developer, I want the generated JSON to contain all candidate evidence, so that a future local LLM can refine hypotheses without re-running or reconstructing static analysis.
44. As a developer, I want the generated JSON to remain LLM-agnostic, so that deterministic analysis can be used independently of any model provider.
45. As a developer, I want the module overview to scale better than the raw class graph, so that the primary human view remains usable in large projects.
46. As a developer, I want filters for candidate, shared collaborators, unassigned classes, and framework dependencies, so that I can focus visual review on one architectural question at a time.
47. As a developer, I want deterministic candidate ordering and scoring, so that repeated analysis can be compared meaningfully.
48. As a developer, I want static analysis to describe candidates as hypotheses rather than confirmed bounded contexts, so that domain ownership decisions remain with developers and domain experts.
49. As a developer, I want the future LLM integration to receive module candidates, evidence, namespace drift, and unassigned code, so that semantic refinement starts from structured facts.
50. As a developer, I want the existing raw architecture outputs to remain available, so that this feature extends discovery without removing lower-level diagnostic evidence.

## Implementation Decisions

- Extend the canonical architecture model with a separate module-candidate result. Raw `clusters` retain their existing graph-theoretical meaning and are not renamed to modules.
- Add module-candidate data to `architecture.json` first. The architecture JSON remains the versioned intermediate representation consumed by renderers and the future LLM context builder.
- Add fields for candidate identity, display name, core members, related/shared members, candidate-adjacent unassigned classes, candidate confidence, evidence, and namespace alignment. Add a top-level unassigned-class result for classes with no candidate relationship.
- Represent class ownership and collaboration separately. A class may have one primary candidate, multiple related candidates, a shared candidate, or no owner.
- Use deterministic weighted evidence. Initial signals include exact or configured name-token matches, namespace/path matches, dependency relationships to candidate core classes, controller entrypoint roles, and supporting test/migration references. Framework-only and generic/shared signals must not create unjustified ownership.
- Keep score components and evidence labels in the output so the weighting can be tuned without changing the conceptual contract.
- Support both inferred terms and project-provided vocabulary. Project configuration is the normal source; CLI options and an explicit configuration file may override or supplement it for temporary and CI use.
- Support configured aliases and namespace patterns per candidate. Configured namespace patterns take precedence over inferred namespace alignment.
- Normalize naming styles into boundary-aware tokens. Matching is case-insensitive and must avoid incidental substring matches.
- Classify source artifacts by role. Controllers are feature entrypoints; tests and migrations are excluded from core membership by default but remain available as supporting evidence; generic bootstrap and framework classes do not establish candidate ownership.
- Treat namespace alignment as independent from module membership. A candidate member outside the expected namespace is reported as namespace drift and may receive an informational suggested location.
- Do not move, rename, or rewrite analyzed-project files. This feature remains read-only with respect to analyzed projects, consistent with the no-footprint decision.
- Add `modules.svg` as a module overview artifact. Keep `graph.svg` as the raw class dependency graph and keep class-level evidence available through a detailed view or filtered class graph.
- Render candidate modules as Graphviz compound clusters or equivalent grouped areas. A candidate may contain core members; shared candidates are shown once; weak shared collaborators and candidate-adjacent unassigned classes use distinct styles.
- Aggregate class dependencies into candidate-to-candidate edges in the module overview. The aggregate includes dependency count and weighted strength; the underlying class edges remain available in the detailed graph.
- Encode the target abstraction type using edge color: interface targets green, concrete targets red, and abstract targets amber. Framework or external edges use a subdued gray treatment where they are shown.
- Encode dependency meaning separately using edge style, arrow treatment, or labels. Colors must not be the only interpretation mechanism.
- Encode confidence using border or grouping treatment and explicit candidate text. Include a legend in both Graphviz-derived visualizations and ensure the fallback SVG communicates the same meanings as far as its format permits.
- Show candidate-adjacent unassigned classes in the module detail view by default and provide a mode to show all unassigned classes.
- Include class file, namespace, role, candidate relationship, confidence, and namespace alignment in the detail representation so a human can investigate drift directly.
- Preserve deterministic ordering for candidates, classes, evidence, and diagram elements to support versioning and comparison.
- Keep bounded-context confirmation out of static analysis. The analyzer produces Module Candidates, not Bounded Contexts or confirmed Modules.
- Extend the normalized reporting view so Graphviz, HTML, Markdown, and the future LLM context can consume candidates without coupling them to the internal object graph.
- Update the architecture JSON schema to describe the new fields while preserving backward compatibility for consumers that only read classes, dependencies, metrics, and raw clusters.

## Testing Decisions

- Tests should assert external behavior: candidate membership, ownership/relatedness, unassigned results, evidence, confidence ordering, namespace alignment, generated JSON shape, diagram semantics, legend presence, and CLI artifact selection. They should not assert private helper calls or implementation-specific traversal order.
- Use the existing `Architecture` model as the primary high-level test seam. Construct known classes and dependencies, run the candidate discovery layer, and inspect the resulting public candidate model.
- Add focused unit tests for token normalization, configured aliases, namespace patterns, role classification, scoring evidence, shared candidates, primary ownership, related candidates, unassigned classes, and namespace drift.
- Add fixtures that deliberately scatter one feature across multiple namespaces and directories, add a generic shared service used by several candidates, and include unrelated legacy classes.
- Add tests proving that controllers can become candidate members while generic bootstrap classes do not merge candidates.
- Add tests proving that tests and migrations are excluded from core membership by default but remain available as supporting evidence.
- Add renderer tests at the normalized view seam, following the existing reporting tests. Assert that module overview output contains grouped candidates, aggregated edges, shared candidates, namespace-drift markers, confidence text, and a complete legend.
- Add raw graph tests proving interface-target edges are green, concrete-target edges are red, abstract-target edges use the configured distinct style, relationship types remain visible, and framework/external dependencies use the subdued style.
- Add tests for the fallback SVG path where Graphviz is unavailable, ensuring it remains non-empty and communicates the relevant candidate and legend information.
- Add CLI tests proving `modules.svg` is generated by default, can be excluded through format selection, and does not alter the existing raw `graph.svg` behavior.
- Add schema validation tests for the extended `architecture.json` artifact and a compatibility case for an architecture model with no module candidates.
- Add deterministic-repeat tests that analyze the same fixture twice and compare candidate ordering, evidence ordering, and scores.
- Add performance coverage against a larger synthetic fixture with many unrelated classes, shared collaborators, and several candidates. The test should verify bounded execution and readable candidate output rather than enforce a premature absolute benchmark.
- Prior art includes the existing architecture model tests, connected-component clustering tests, parser tests, renderer tests in `tests/Unit/Reporting/`, and command tests in `tests/Unit/Application/Command/`.

## Out of Scope

- Confirming bounded contexts, business ownership, or ubiquitous language from static structure alone.
- Requiring or implementing the postponed LLM integration. The output should prepare for it but remain fully useful without it.
- Automatically moving files, changing namespaces, rewriting imports, or performing architectural refactoring in analyzed projects.
- Replacing raw connected-component clusters with module candidates or changing the meaning of existing raw clusters.
- Treating technical namespaces such as `Controller`, `Model`, `Infrastructure`, or `Domain` as confirmed modules solely because they exist.
- Building a complete interactive browser-based drill-down application. The initial visualization is static Graphviz/SVG output with filtering support where practical.
- Inferring domain synonyms with a language model or external service.
- Automatically assigning every class to exactly one candidate.
- Making tests, migrations, framework adapters, or shared infrastructure disappear from the architecture artifact; they may be excluded from core membership but remain traceable.
- Implementing automatic architecture decisions based only on candidate confidence.
- Replacing the existing Graphviz renderer with a third-party visualization platform.

## Further Notes

The most important distinction in this design is between a **Cluster**, a **Module Candidate**, a **Module**, and a **Bounded Context**. A cluster is a graph result. A module candidate is an explainable static-analysis hypothesis. A module is a developer-maintained architectural boundary. A bounded context is a confirmed domain boundary. The artifact and visualization must preserve those levels rather than collapsing them into one label.

For the small Todo example, the expected result is one strong Todo module candidate spanning application, domain, interface, infrastructure, and persistence roles, with tests and migrations treated as supporting material. In a larger project, the same model must support multiple candidates, shared candidates such as User or Identity, namespace drift, and a substantial unassigned area.

The primary review workflow is: inspect `modules.svg`, select a candidate, inspect its module details and evidence, review shared collaborators and namespace drift, then make a human architectural decision. The future LLM may help refine that judgment, but it should consume the structured evidence rather than replace it.

The first implementation should prioritize the candidate model and scoring evidence before investing in visual polish. This keeps the machine-readable contract authoritative and makes the diagrams straightforward consumers of a tested analysis result.
