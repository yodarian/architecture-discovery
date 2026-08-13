# 06 — Optional LLM context suggestions

**What to build:** An opt-in LLM flow that receives only normalized architecture data and structured summaries, and returns context suggestions without depending on raw repository access.

**Blocked by:** 05 — Metrics, clustering, and generated artifacts

**Status:** ready-for-agent

- [ ] Acceptance criterion: the LLM path is explicitly optional and separate from the core analysis pipeline
- [ ] Acceptance criterion: the LLM receives the architecture model and summaries instead of raw source code
- [ ] Acceptance criterion: suggestions are returned in a structured format and clearly separate detected facts from suggested interpretations
