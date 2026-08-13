# 04 — CakePHP association analysis

**What to build:** A CakePHP-specific analysis layer that interprets ORM and dynamic model relationships and adds framework-aware dependency edges without breaking the generic PHP analysis.

**Blocked by:** 02 — Architecture model and CLI contract, 03 — Static PHP discovery and dependency extraction

**Status:** ready-for-agent

- [ ] Acceptance criterion: ORM associations such as belongsTo, hasMany, hasOne, and belongsToMany are recognized
- [ ] Acceptance criterion: dynamic table access patterns such as fetchTable and loadModel are handled where statically possible
- [ ] Acceptance criterion: CakePHP-specific relations are represented in the architecture model with clear type and weighting
