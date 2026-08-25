# 03 — Expose Namespace Drift in Module Evidence

**What to build:** A developer can inspect a module candidate and see which members are physically outside its expected namespace, including their actual namespace, role, confidence, evidence, and an informational suggested location.

**Blocked by:** 02 — Report Shared, Unassigned, and Supporting Code

**Status:** ready-for-agent

- [ ] Treat namespace alignment as distinct from candidate membership.
- [ ] Infer alignment from candidate vocabulary and support configured namespace patterns.
- [ ] Identify candidate members whose current namespaces are outside the expected pattern.
- [ ] Report aligned and drifting members separately in the candidate evidence.
- [ ] Include actual namespace, source location, role, confidence, and evidence for drifting classes.
- [ ] Provide a suggested target location without changing files, namespaces, imports, or analyzed-project contents.
- [ ] Add tests for aligned classes, scattered legacy classes, configured patterns, and informational suggestions.
