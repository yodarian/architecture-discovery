# 03 — Static PHP discovery and dependency extraction

**What to build:** A deterministic static analysis pipeline that recursively finds PHP files, parses classes and dependencies, and produces a reproducible architecture overview for generic PHP projects.

**Blocked by:** 02 — Architecture model and CLI contract

**Status:** ready-for-agent

- [ ] Acceptance criterion: PHP source files are discovered recursively from the project root
- [ ] Acceptance criterion: classes, interfaces, traits, namespaces, inheritance, and type-based dependencies are extracted
- [ ] Acceptance criterion: the output is emitted as a versioned architecture model and is reproducible for the same input
