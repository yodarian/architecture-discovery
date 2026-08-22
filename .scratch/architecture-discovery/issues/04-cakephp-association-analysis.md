# 04 — CakePHP association analysis

**What to build:** A CakePHP-specific analysis layer that interprets ORM and dynamic model relationships and adds framework-aware dependency edges without breaking the generic PHP analysis.

**Blocked by:** 02 — Architecture model and CLI contract, 03 — Static PHP discovery and dependency extraction

**Status:** ready-for-human

- [x] Acceptance criterion: ORM associations such as belongsTo, hasMany, hasOne, and belongsToMany are recognized
- [x] Acceptance criterion: dynamic table access patterns such as fetchTable and loadModel are handled where statically possible
- [x] Acceptance criterion: CakePHP-specific relations are represented in the architecture model with clear type and weighting

## Implementation Notes

- Added the dedicated `CakePhpAnalyzer` AST adapter for `belongsTo`, `hasMany`, `hasOne`, and `belongsToMany` ORM calls.
- Added detection for `fetchTable` and `loadModel`, including literal target extraction and explicit `static: false` metadata for unresolved arguments.
- Static table/model names resolve to discovered classes using CakePHP table naming conventions such as `Customers` to `CustomersTable`.
- ORM relations emit `orm_relation` edges with weight `3`; dynamic model calls emit `dynamic_call` edges with weight `2`.
- Generic PHP analysis remains active and framework analysis is additive.

## Validation

- PHPUnit: 36 tests and 117 assertions passing.
- CLI-level coverage verifies ORM and dynamic CakePHP edges are emitted in `architecture.json` with their expected types and weights.
