# 08 — Prepare the LLM Context for Module Candidates

**What to build:** The normalized optional LLM context can consume module candidates, evidence, shared relationships, unassigned classes, and namespace drift while remaining provider-agnostic and useful without an LLM.

**Blocked by:** 01 — Discover a Basic Module Candidate; 02 — Report Shared, Unassigned, and Supporting Code; 03 — Expose Namespace Drift in Module Evidence

**Status:** ready-for-agent

- [ ] Expose module candidates through the normalized reporting view.
- [ ] Include candidate evidence, confidence, and membership categories.
- [ ] Include primary ownership, related candidates, shared candidates, and unassigned classes.
- [ ] Include namespace alignment and namespace drift.
- [ ] Preserve existing normalized-view consumers and architecture fields.
- [ ] Do not call an LLM or infer bounded contexts in the static pipeline.
- [ ] Add tests proving the context contains structured evidence and remains provider-agnostic.
