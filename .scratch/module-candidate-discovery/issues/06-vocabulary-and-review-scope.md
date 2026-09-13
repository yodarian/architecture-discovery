# 06 — Configure Vocabulary and Review Scope

**What to build:** Developers can provide project-specific terms, aliases, namespace patterns, exclusions, and temporary analysis overrides, and can focus visual review on one candidate or on shared, unassigned, or framework code.

**Blocked by:** 01 — Discover a Basic Module Candidate

**Status:** ready-for-agent

- [x] Support project configuration as the normal vocabulary and scope source.
- [x] Support CLI options and an explicit configuration file as overrides or supplements.
- [x] Support aliases such as Task and WorkItem for a Todo candidate.
- [x] Support configured namespace patterns for alignment.
- [x] Support configurable exclusion of tests and migrations from module membership.
- [x] Support candidate, shared, unassigned, and framework filtering where the output format permits.
- [x] Record configuration provenance in the generated architecture artifact.
- [x] Add tests for configuration precedence, aliases, namespace patterns, exclusions, and filters.
