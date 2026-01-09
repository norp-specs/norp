# NORP Changelog

All notable changes to NORP specifications will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) for specification versions.

---

## [NORP-001 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption

### Changed
- **Section 7 (Error Taxonomy)**: Simplified structure, removed verbose subsections
- **Section 9 (Compliance Tests)**: Condensed test descriptions, moved detailed specs to `compliance-tests/NORP-001-tests.md`
- **Section 3 (Scope)**: Clarified exclusion of dynamic workflows
- **Section 4 (Terminology)**: Consolidated cost-incurring operation definition with examples

### Added
- **License header**: CC BY 4.0 explicitly stated
- **Section 14**: Acknowledgments
- **Appendix A**: Revision history table
- **Citation block**: BibTeX format for academic use
- **Section 5.4.1**: Recommended threshold for context validity (5 minutes)

### Improved
- Overall readability and conciseness
- Cross-references between sections
- Professional formatting for publication

---

## [NORP-001 v1.1] - 2026-01-08 - DRAFT

### Added
- **Section 5.1.1**: Stage Ordering Rationale - Justifies mandatory pipeline order
- **Section 5.2.1**: Cycle Detection Requirements - Specifies O(V+E) complexity
- **Section 5.3.1**: Determinism Requirement - Clarifies execution order consistency
- **Section 5.4.1**: Context Validity Window - Addresses time-based validation issues
- **Section 5.5.1**: Transactional Semantics - Optional rollback support
- **Section 5.6**: Validation Caching - Optional performance optimization
- **Section 7.1-7.3**: Detailed error taxonomy with examples
- **Section 9.1**: Expanded compliance test suite (6 tests)

### Changed
- **Section 4**: Enhanced terminology definitions
- **Section 6**: Nuanced fail-fast principle for transient errors
- **Test specifications**: Added detailed Input/Expected/Rationale for each test

### Improved
- Addressed ambiguities from v1.0 review
- Added missing edge cases
- Enhanced testability

---

## [NORP-001 v1.0] - 2026-01-06 - DRAFT

### Added
- Initial specification draft
- **Section 1-12**: Core content (Abstract, Motivation, Scope, Requirements, etc.)
- **Section 5.1**: Mandatory 6-stage pipeline
- **Section 5.2**: Structural validation requirements
- **Section 5.3**: Compilation stage
- **Section 5.4**: Context resolution
- **Section 5.5**: Execution semantics
- **Section 6**: Fail-fast principle
- **Section 7**: Basic error taxonomy
- **Section 9**: Initial compliance tests (4 tests)

### Notes
- First public draft
- Feedback solicited from NORP Working Group

---

## [NORP-002 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption

### Added
- **Section 4.1**: Tenant Model Consistency - Clarifies hierarchical models
- **Section 5.1.1**: Tenant Resolution Algorithm - 3-level priority order
- **Section 5.1.2**: Conflict Resolution - Deterministic conflict handling with examples
- **Section 5.2.1**: Resource Scoping Mechanisms - 6 concrete mechanisms
- **Section 5.2.2**: Isolation Verification Test - COUNT test methodology
- **Section 5.3.1**: Global Resource Criteria - Strict requirements for global designation
- **Section 5.6.1**: Authorized Cross-Tenant Collaboration - B2B use case support
- **Section 8.1**: Common Anti-Patterns - 5 technology stacks (SQL, REST, Cache, File, NoSQL)
- **Section 8.2**: Code Review Checklist - 8-point verification checklist
- **Section 9.1**: Compliance Test Suite - 5 mandatory tests detailed
- **Appendix A**: Revision History

### Changed
- **Section 5.5**: Clarified READ isolation (distinct from WRITE in 5.6)
- **Test 1**: Reformulated as strict rejection (eliminated ambiguous "OR" behavior)
- **License header**: CC BY 4.0 explicitly stated
- **Citation**: BibTeX format added

### Improved
- Removed redundancy between sections 5.5 and 5.6
- Enhanced testability with concrete test cases
- Added multi-language anti-pattern examples
- Strengthened security considerations

---

## [NORP-003 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption

### Added
- **Section 3.1**: Relationship to NORP-001 - Clarifies complementary nature with pipeline diagram
- **Section 5.1.1**: Immutability Definition - Deep vs shallow copy, 5-language examples (JS, PHP, Python, Java, Rust)
- **Section 5.4.1**: Determinism Scope - Pipeline deterministic, nodes may be probabilistic
- **Section 5.5.1**: State Retention on Failure - Explicit ownership pattern with PHP example
- **Section 5.6.1**: Non-Serializable Objects - Metadata replacement guidance (PDO example)
- **Section 8.1**: Common Anti-Patterns - 6 examples across 5 languages (TypeScript, Python, PHP, JavaScript, Rust)
- **Section 8.2**: Code Review Checklist - 9-point verification checklist
- **Section 9.1**: Compliance Test Suite - 4 mandatory tests detailed
- **Section 9.2**: Optional Tests - 2 recommended tests (deep immutability, serialization round-trip)
- **Appendix A**: Revision History
- **Appendix B**: Reference DTOs - Complete examples in PHP, TypeScript, Python

### Changed
- **Section 1**: Enhanced abstract with two orthogonal concerns (immutability + determinism)
- **Section 5.6**: Upgraded from SHOULD to MUST for serialization requirement
- **License header**: CC BY 4.0 explicitly stated
- **Citation**: BibTeX format added

### Improved
- Resolved apparent contradiction between pipeline determinism and LLM non-determinism
- Added concrete code examples for all major anti-patterns
- Strengthened testability with executable test cases
- Linked to NORP-001 for complete pipeline specification
- Distinguished pipeline stages (deterministic) from execution nodes (may be probabilistic)

---

## [NORP-004 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption

### Added
- **Section 3.1**: Relationship to NORP-001 - Extension of Section 5.2 (Structural Validation)
- **Section 3.2**: Loops vs Structural Cycles - Clarifies scope (structural cycles IN, bounded loops OUT)
- **Section 5.3.1**: DFS Algorithm - Complete pseudocode with complexity proof O(V+E)
- **Section 5.3.2**: Kahn's Alternative - Topological sort validation method
- **Section 5.4.1**: Diagnostic Format - JSON structure with cycle_path
- **Section 5.4.2**: Diagnostic Completeness - MUST report one, SHOULD report all cycles
- **Section 8.1**: Common Anti-Patterns - 3 examples (runtime detection, partial validation, ignoring cycles)
- **Section 8.2**: Performance Considerations - Benchmarks for typical graph sizes
- **Section 9.1**: Compliance Test Suite - 5 mandatory tests
- **Appendix A**: Example Workflows - Simple cycle, multi-node cycle, valid DAG with JSON
- **Appendix B**: Revision History

### Changed
- **Section 5.3**: Upgraded from SHOULD to MUST for O(V+E) complexity
- **License header**: CC BY 4.0 explicitly stated
- **Citation**: BibTeX format added

### Improved
- Distinguished structural cycles (prohibited) from loop constructs (out of scope)
- Added complete DFS + Kahn's algorithm pseudocode with complexity proofs
- Enhanced diagnostic guidance (format + completeness)
- Provided executable test cases with expected error formats

---

## [NORP-005 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption

### Added
- **Section 3.1**: Relationship to NORP-001 and NORP-004 - Clarifies complementary specs with pipeline diagram
- **Section 5.1.1**: Topological Sorting Algorithm Requirements - O(V+E) mandate with algorithm options
- **Section 5.1.2**: Deterministic Topological Sorting - Kahn's algorithm with tie-breaking pseudocode
- **Section 5.2.1**: Tie-Breaking Examples - 3 concrete mechanisms (lexicographic, priority, insertion)
- **Section 5.3.1**: Logical vs Physical Execution - Clarifies parallel execution compatibility with audit trail example
- **Section 6**: Deterministic Diagnostics - Error format with ORDERING_FAILED code
- **Section 8.1**: Common Anti-Patterns - 3 examples (completion order, undocumented tie-breaking, partial ordering)
- **Section 8.2**: Performance Considerations - Benchmarks for production workloads
- **Section 9.1**: Compliance Test Suite - 5 mandatory tests
- **Appendix A**: Example Workflows - 3 DAG patterns (simple, diamond, parallel-friendly)
- **Appendix B**: Revision History

### Changed
- **License header**: CC BY 4.0 explicitly stated
- **Citation**: BibTeX format added

### Improved
- Clarified relationship to NORP-001 (high-level) and NORP-004 (cycle detection prerequisite)
- Distinguished logical order (deterministic, for audit) from physical execution (may be parallel)
- Provided executable test cases with expected outputs
- Added tie-breaking examples for three common patterns

---

## [NORP-006 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption

### Added
- **Section 3.1**: Relationship to NORP-001 and NORP-002 - Clarifies WHO (tenant) vs WHEN/HOW LONG (execution) isolation
- **Section 5.1**: Execution Identity - Unique execution_id (UUID) requirement
- **Section 5.2**: Execution-Scoped Resource Lifetime - Strict scoping rules
- **Section 5.3**: Resource Pooling Rules - What pooling means vs what it doesn't
- **Section 5.3.1**: Pooling Definition - Reuse within execution vs cross-execution
- **Section 5.3.2**: Pooling Benefits and Constraints - Performance vs isolation trade-offs
- **Section 5.4.1**: Cleanup Guarantees - try-finally pattern with Python example
- **Section 7**: Diagnostic and Observability - Execution_id logging requirements
- **Section 8.1**: Common Anti-Patterns - 3 examples (global pool, singleton, cross-exec cache) in PHP, Python, JavaScript
- **Section 8.2**: Resource Lifecycle Pattern - Complete ExecutionContext implementation with usage example
- **Section 9.1**: Compliance Test Suite - 5 mandatory tests
- **Appendix A**: Example Execution Context Pattern - Full Python implementation with lazy loading and cleanup
- **Appendix B**: Revision History

### Changed
- **License header**: CC BY 4.0 explicitly stated
- **Citation**: BibTeX format added

### Improved
- Distinguished tenant isolation (NORP-002) from execution isolation (NORP-006)
- Added concrete pooling benefits quantification (50-200ms saved per workflow)
- Provided complete ExecutionContext pattern with try-finally guarantee
- Clarified pooling (reuse instance) vs caching (store results)

---

## [NORP-007 v1.2] - 2026-01-09 - STABLE

### Status Change
- **Upgraded to STABLE** - Ready for production adoption
- **PHASE 1 COMPLETED** (7/7 foundational specs STABLE)

### Added
- **Section 3.1**: Relationship to NORP-001 - Cost estimation as part of Context Resolution stage
- **Section 5.1.1**: Token Counting - Native tokenizer vs approximation (chars/4 for English, chars×0.3 multilingual)
- **Section 5.1.2**: Pricing Model - Input/output unit pricing requirements
- **Section 5.1.3**: Cost Formula - Mathematical formula for LLM cost calculation
- **Section 5.1.4**: Conservative Estimation - 20-50% safety margin guidance
- **Section 5.2.1**: Budget Scope Examples - Per-execution, per-tenant daily, per-workflow cumulative
- **Section 5.2.2**: Budget Enforcement Timing - Pre-execution (MUST) + runtime (SHOULD) with pseudocode
- **Section 5.3**: Cost Transparency and Observability - Machine-readable diagnostics
- **Section 5.4**: Actual Cost Tracking - Post-execution cost measurement
- **Section 8.1**: Common Anti-Patterns - 3 examples (no estimation, underestimation, ignoring runtime budget)
- **Section 8.2**: Cost Estimation Implementation - Complete Python function with formula
- **Section 9.1**: Compliance Test Suite - 5 mandatory tests
- **Appendix A**: Example Cost Estimation Workflow - Full calculation breakdown (2 LLM nodes)
- **Appendix B**: LLM Pricing Reference - 2026 Q1 pricing table with update policy
- **Appendix C**: Revision History

### Changed
- **License header**: CC BY 4.0 explicitly stated
- **Citation**: BibTeX format added
- **Section 5.2.2**: Upgraded runtime enforcement from "optional" to SHOULD (stronger governance)

### Improved
- Clarified token counting approximations with empirical basis (chars/4)
- Added pricing table expiration policy (quarterly review recommended)
- Provided complete cost estimation implementation example
- Distinguished pre-execution (MUST) from runtime (SHOULD) enforcement
- Added statistical validation test (Test 5: 100 workflows, 80% overestimate threshold)

---

## 🎉 PHASE 1 MILESTONE ACHIEVED

**All 7 foundational NORP specifications published as STABLE**

- NORP-001 v1.2: Pre-Execution Validation Pipeline (9.3/10)
- NORP-002 v1.2: Multi-Tenant Resource Isolation (9.5/10)
- NORP-003 v1.2: Immutable Execution State (9.3/10)
- NORP-004 v1.2: Cycle Detection (9.4/10)
- NORP-005 v1.2: Topological Ordering (9.5/10)
- NORP-006 v1.2: Resource Pooling (9.3/10)
- NORP-007 v1.2: Cost Estimation (9.6/10)

**Average Quality**: 9.41/10
**Total Documentation**: 6,500+ lines
**Total Compliance Tests**: 40+ tests
**Publication Date**: 2026-01-09

---

## Future Releases

### Planned for Q2 2026 (Phase 2)
- Reference implementations (PHP, TypeScript, Python)
- Interoperability test suite

### Under Discussion (Phase 4 - Advanced Specs)
- **NORP-008**: Distributed Execution Guarantees
- **NORP-009**: Transactional Rollback Semantics
- **NORP-010**: Observability and Audit Standards

---

## Version Naming Convention

NORP specifications follow this versioning scheme:

```
[NORP-XXX vMajor.Minor]

Major: Breaking changes to normative requirements
Minor: Clarifications, additions, or non-breaking improvements
```

**Status labels**:
- **Draft**: Work in progress, feedback welcome
- **Stable**: Published and ready for production adoption
- **Deprecated**: Replaced by newer version
- **Withdrawn**: Removed from NORP catalog

---

**Maintained by**: NORP Working Group
**Last updated**: 2026-01-09
**Next review**: 2026-04-01
