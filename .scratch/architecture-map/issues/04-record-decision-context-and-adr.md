# 04 — Record the decision in CONTEXT.md and an ADR

**What to build:** This repo's own `CONTEXT.md` and a new ADR accurately describe the tool's current behavior and the reasoning behind it, replacing the now-outdated CONTEXT.md-seeding policy with the Architecture Map and no-footprint-in-analyzed-repo decisions.

**Blocked by:** 01, 02, 03

**Status:** done

- [x] Acceptance criterion: `CONTEXT.md`'s "Next Steps" sentence about seeding `CONTEXT.md` in analyzed projects is removed
- [x] Acceptance criterion: `CONTEXT.md` documents the Architecture Map artifact and the policy that `analyse` never writes into the analyzed project
- [x] Acceptance criterion: a new ADR under `docs/adr/` records why CONTEXT.md-seeding was removed and why `analyse` never writes into analyzed repos, referencing the actual shipped behavior from tickets 01–03

## Comments

Implemented in commit `75d25c0`. Added `docs/adr/0001-no-footprint-in-analyzed-repo.md` (first ADR in the repo). This closes out the architecture-map effort — all four tickets are now done.
