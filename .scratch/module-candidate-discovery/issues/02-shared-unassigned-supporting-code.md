# 02 — Report Shared, Unassigned, and Supporting Code

**What to build:** Large or partially organized projects can show classes that belong to a candidate, collaborate with several candidates, form a strong shared candidate, or remain unassigned. Supporting code is visible without defining business ownership.

**Blocked by:** 01 — Discover a Basic Module Candidate

**Status:** ready-for-agent

- [ ] Allow one primary candidate, multiple related candidates, or no owner for a class.
- [ ] Suggest a strong shared concept such as User as its own candidate when evidence supports it.
- [ ] Keep weakly shared utilities outside candidate ownership while reporting their collaborations.
- [ ] Report classes with insufficient evidence as unassigned.
- [ ] Classify source elements by role, including domain, application, interface, infrastructure, persistence, test, migration, framework, shared, and unknown.
- [ ] Exclude tests and migrations from core membership by default while retaining them as supporting evidence.
- [ ] Prevent generic bootstrap and framework classes from establishing candidate ownership.
- [ ] Preserve controllers as eligible feature entrypoints.
- [ ] Add tests using multiple candidates, shared services, unrelated legacy classes, tests, migrations, and generic bootstrap classes.
