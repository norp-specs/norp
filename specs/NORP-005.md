# NORP-005
## Deterministic Topological Ordering for AI Orchestration Workflows

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Stable

### Category
Compilation and Execution Semantics

### Version
1.2

### Date
2026-01-09

### Authors
NORP Working Group

---

## 1. Abstract

This specification defines deterministic topological ordering requirements for AI orchestration workflows represented as directed graphs.

It standardizes how compliant systems MUST produce a deterministic logical execution order, including mandatory tie-breaking rules, parallel execution compatibility, diagnostics, and a compliance test suite.

The objective is to ensure reproducibility, auditability, and predictable execution across complex AI workflows.

---

## 2. Motivation

AI orchestration systems frequently execute workflows as dependency graphs. When multiple valid topological orders exist, non-deterministic ordering causes:

- **Non-reproducible behavior** and audit trails
- **Inconsistent cost and latency profiles** (different order = different timing = different costs)
- **Difficult debugging** (cannot reproduce issue if order varies)
- **Divergent results** across deployments and runtimes

Deterministic ordering is therefore a production requirement, not an implementation detail.

---

## 3. Scope

This specification applies to systems that compile workflows into an execution plan derived from a directed dependency graph.

### 3.1 Relationship to NORP-001 and NORP-004

This specification is **complementary** to:

- **NORP-001 Section 5.3** (Compilation): Defines high-level requirement for deterministic execution ordering
- **NORP-004** (Cycle Detection): Defines mandatory rejection of cyclic graphs during structural validation

**NORP-005 specifies** how deterministic ordering MUST be implemented and verified **after** a graph is validated as acyclic.

A compliant system MUST implement:
- **NORP-004** (or equivalent) to ensure input graph is acyclic
- **NORP-005** to produce deterministic ordering from validated DAG

**Relationship summary**:
```
NORP-004: Graph validation (reject if cycle)
    ↓
NORP-005: Deterministic ordering (if valid DAG)
    ↓
NORP-001: Execution (using deterministic order)
```

---

## 4. Terminology

**Workflow**: A directed graph of nodes and edges.

**Node**: A unit of execution.

**Edge**: A directed dependency relationship (if edge A→B exists, A must execute before B).

**Topological order**: An ordering of nodes such that for every edge U→V, U appears before V in the sequence.

**Logical order**: A deterministic sequence derived from dependencies and tie-breaking rules (used for auditability).

**Physical execution**: The runtime scheduling of node execution, which may be parallelized for performance.

**Tie-breaking rule**: A deterministic rule applied when multiple nodes are equally eligible for scheduling.

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Normative Requirements

### 5.1 Deterministic Ordering

Given an **identical workflow definition** and **identical tie-breaking configuration**, a compliant system MUST produce the **same logical execution order** across runs.

Execution order MUST be **independent of**:
- Runtime timing or latency
- Node insertion order in the input representation
- Infrastructure variability (server load, network delays)
- Hash function randomization

If the workflow is cyclic, the system MUST **reject it prior to ordering**, consistent with NORP-004.

---

#### 5.1.1 Topological Sorting Algorithm Requirements

Compliant implementations MUST use a topological sorting approach that:

- Operates in **O(V + E)** time for the graph traversal component
- Produces a **deterministic logical order** by applying a deterministic tie-breaking rule whenever multiple nodes are eligible
- **Rejects the workflow** if a full order cannot be produced (cycle detected)

Implementations MAY use **Kahn's algorithm**, **DFS post-order**, or equivalent methods, provided determinism and rejection semantics are satisfied.

---

#### 5.1.2 Deterministic Topological Sorting (Optional Reference)

Implementations MAY provide an explicit deterministic topological sorting algorithm.

When multiple nodes are eligible for execution at the same dependency level, the algorithm MUST apply the documented tie-breaking rule consistently.

The following reference illustrates **Kahn's algorithm with deterministic tie-breaking** using lexicographic ordering.

**Pseudocode**:

```python
def topological_sort_deterministic(graph):
    """
    Kahn's algorithm with deterministic tie-breaking.

    Returns: Deterministic logical execution order
    Complexity: O(V log V + E) due to sorting
    """
    in_degree = {node: 0 for node in graph}

    # Calculate in-degrees
    for node in graph:
        for dep in graph[node]:
            in_degree[dep] += 1

    # Deterministic selection of zero in-degree nodes
    queue = sorted([n for n in graph if in_degree[n] == 0])  # ← Tie-breaking
    result = []

    while queue:
        current = queue.pop(0)
        result.append(current)

        newly_eligible = []
        for dep in graph[current]:
            in_degree[dep] -= 1
            if in_degree[dep] == 0:
                newly_eligible.append(dep)

        # Deterministic reinsertion (tie-breaking)
        queue = sorted(queue + newly_eligible)  # ← Tie-breaking

    # Cycle detection (if not all nodes sorted)
    if len(result) != len(graph):
        raise CycleDetectedError("Topological sort failed: cycle exists")

    return result
```

**Key for determinism**: `sorted()` calls ensure consistent ordering when multiple nodes have zero in-degree.

**Complexity**: O(V log V + E) due to sorting, acceptable for production.

**Note**: This example is **non-normative** and provided for clarity. Implementations MAY use alternative algorithms provided all normative requirements are satisfied.

---

### 5.2 Tie-Breaking Rules

When multiple nodes are eligible for scheduling at the same step, the system MUST apply a **consistent tie-breaking rule**.

The tie-breaking rule MUST be:
- **Deterministic** (same inputs = same output)
- **Documented** (publicly specified)
- **Stable across runs** (does not change between executions)

Valid tie-breaking mechanisms include, but are not limited to:
- Lexicographic ordering of node identifiers
- Stable ordering key fields (e.g., `created_at`, `insertion_index`)
- Explicit priority metadata

Systems MUST document which tie-breaking rule is used and how conflicts are resolved.

---

#### 5.2.1 Tie-Breaking Examples

**Example 1: Lexicographic by Node ID**

Nodes: `Z`, `A`, `M` (no dependencies, all eligible simultaneously)

Tie-breaking: Ascending lexicographic order by node ID
Logical order: `["A", "M", "Z"]`

---

**Example 2: Explicit Priority Metadata**

```json
{
  "nodes": [
    {"id": "A", "priority": 2, "depends_on": []},
    {"id": "B", "priority": 1, "depends_on": []}
  ]
}
```

Tie-breaking: Ascending numeric priority, then lexicographic by ID
Logical order: `["B", "A"]` (B has priority 1 < A priority 2)

---

**Example 3: Insertion Order Preservation**

Nodes inserted in sequence: `A`, then `C`, then `B`

Tie-breaking: Preserve insertion order
Logical order: `["A", "C", "B"]`

**Note**: Insertion order requires stable metadata (e.g., `insertion_index` field) to be deterministic across system restarts.

---

### 5.3 Parallel Execution Compatibility

Deterministic logical ordering MUST coexist with parallel physical execution.

The system MUST be able to:
- Produce a **deterministic logical order** for auditability and reproducibility
- Identify which nodes are **eligible to run in parallel** when dependencies allow

Parallel execution MAY occur when nodes have no dependency relationship.

---

#### 5.3.1 Logical Order vs Physical Execution

**Logical order** is independent of runtime timing and represents the **deterministic dependency-respecting sequence**.

**Physical execution** is the actual runtime scheduling, which MAY be parallelized for performance.

**Example**:
- **Logical order**: `[A, B, C, D]`
- **Physical execution**:
  - Time T0: A executes
  - Time T1: B and C execute **in parallel** (both depend only on A)
  - Time T2: D executes (after B and C complete)

The system MUST:
- Produce logical order deterministically (always `[A, B, C, D]`)
- MAY execute B and C in parallel (physical optimization)
- Log events and audit trails using **logical order**, not physical completion order

**Audit trail example**:
```json
{
  "execution_id": "exec_123",
  "logical_order": ["A", "B", "C", "D"],
  "physical_execution": [
    {"node": "A", "started_at": "10:00:00", "completed_at": "10:00:05"},
    {"node": "B", "started_at": "10:00:05", "completed_at": "10:00:10"},
    {"node": "C", "started_at": "10:00:05", "completed_at": "10:00:08"},
    {"node": "D", "started_at": "10:00:10", "completed_at": "10:00:15"}
  ]
}
```

**Note**: C completed before B physically, but logical order remains `[B, C]` (deterministic).

---

## 6. Deterministic Diagnostics

If ordering fails, the system MUST return a **deterministic diagnostic**.

Ordering failure errors MUST include:
- **Error type**: `STRUCTURAL_ERROR`
- **Error code**: `ORDERING_FAILED`
- **Reason** field identifying the failure class

Valid reasons include:
- `CYCLE_DETECTED` (cycle exists, ordering impossible)
- `MISSING_NODE_REFERENCE` (node depends on non-existent node)
- `INVALID_DEPENDENCY` (malformed dependency structure)

**Minimal diagnostic example**:

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "ORDERING_FAILED",
  "reason": "CYCLE_DETECTED",
  "message": "Topological order could not be produced due to cycle in graph"
}
```

---

## 7. Security Considerations

Non-deterministic ordering can be exploited to:
- **Hide malicious behavior** behind timing variance (different execution order = different observable behavior)
- **Produce inconsistent audit trails** (compliance violations undetectable)
- **Trigger cost amplification patterns** through unpredictable scheduling

Deterministic ordering is therefore part of **workflow integrity** and **operational security**.

---

## 8. Implementation Guidance (Non-Normative)

### 8.1 Common Anti-Patterns

#### Anti-Pattern 1: Relying on Runtime Completion Order

❌ **BAD**: Infer execution order from which nodes finished first
```javascript
// Wait for all nodes to complete, record completion order
const order = [];
nodes.forEach(n => n.on('complete', () => order.push(n.id)));
// ❌ Non-deterministic (depends on runtime timing)
```

✅ **GOOD**: Persist logical order from compilation, independent of runtime timing
```javascript
// Compute logical order BEFORE execution
const logicalOrder = topologicalSort(workflow.graph);
// Execute using logical order (or parallelized respecting dependencies)
executeWorkflow(workflow, logicalOrder);
```

---

#### Anti-Pattern 2: Undocumented Tie-Breaking

❌ **BAD**: Depend on hash map iteration order
```python
# Python dict iteration order (implementation-dependent before 3.7)
eligible = {node: data for node, data in graph.items() if ready(node)}
next_node = list(eligible.keys())[0]  # ❌ Non-deterministic
```

✅ **GOOD**: Explicit lexicographic ordering or stable keys
```python
eligible = [node for node in graph if ready(node)]
next_node = sorted(eligible)[0]  # ✅ Deterministic (lexicographic)
```

---

#### Anti-Pattern 3: Partial Graph Ordering

❌ **BAD**: Order only reachable nodes from a selected root
```php
// Only order nodes reachable from entry point
$order = orderFromRoot($graph, $entryNode);
```

✅ **GOOD**: Order the complete workflow graph
```php
// Order ALL nodes (including disconnected components)
$order = topologicalSort($graph->getAllNodes());
```

**Why**: Disconnected nodes may contain cycles (detectable only with full graph validation).

---

### 8.2 Performance Considerations (Non-Normative)

Typical topological sorting performance on production workloads:
- **10 nodes**: <1ms
- **100 nodes**: ~5ms
- **1,000 nodes**: ~50ms
- **10,000 nodes**: ~500ms

With deterministic tie-breaking (sorting), complexity becomes O(V log V + E), which is acceptable for production.

---

## 9. Compliance

A system is **NORP-005 compliant** if:
- It implements all mandatory requirements (Sections 5 and 6)
- It rejects cyclic workflows before ordering (NORP-004 compliance assumed)
- It produces deterministic logical ordering across repeated runs
- It passes all mandatory compliance tests

### 9.1 Compliance Test Suite

**Test 1: Deterministic Order - Simple DAG**

**Input**:
```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]}
  ]
}
```

**Action**: Validate and compile twice

**Expected**: Identical logical order both times: `["A", "B"]`

**Rationale**: Proves basic determinism on simple dependency chain.

---

**Test 2: Tie-Breaking Consistency**

**Input**:
```json
{
  "nodes": [
    {"id": "Z", "depends_on": []},
    {"id": "A", "depends_on": []},
    {"id": "M", "depends_on": []}
  ]
}
```

**Tie-breaking rule**: Lexicographic ascending by node ID

**Action**: Compile twice

**Expected**: Identical logical order both times: `["A", "M", "Z"]`

**Rationale**: Proves tie-breaking rule is applied consistently when multiple nodes are equally eligible.

---

**Test 3: Diamond Pattern Determinism**

**Input**:
```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]},
    {"id": "C", "depends_on": ["A"]},
    {"id": "D", "depends_on": ["B", "C"]}
  ]
}
```

**Expected**:
- Logical order **respects dependencies** (A before B/C, B/C before D)
- Order is **consistent across runs**
- If tie-breaking is lexicographic by ID, logical order is: `["A", "B", "C", "D"]`

**Valid alternative** (if tie-breaking differs): `["A", "C", "B", "D"]` (consistently)

**Rationale**: Proves determinism on graphs with multiple valid orders.

---

**Test 4: Parallel Eligibility Identification**

**Input**: (Same diamond pattern as Test 3)

**Expected Output**:
```json
{
  "logical_order": ["A", "B", "C", "D"],
  "parallel_groups": [
    {"level": 0, "nodes": ["A"]},
    {"level": 1, "nodes": ["B", "C"], "parallel": true},
    {"level": 2, "nodes": ["D"]}
  ]
}
```

**Rationale**: Verifies that:
- Logical execution order is deterministic (`[A, B, C, D]`)
- Nodes B and C are identified as **parallel-eligible** (same dependency level)
- Node D is **not eligible** until all dependencies (B and C) complete
- Parallelism does not alter logical ordering guarantees

---

**Test 5: Cycle Rejection Cross-Check**

**Input**:
```json
{
  "nodes": [
    {"id": "A", "depends_on": ["B"]},
    {"id": "B", "depends_on": ["A"]}
  ]
}
```

**Expected**:
- **Rejection prior to ordering** (NORP-004 compliance)
- Error type: `STRUCTURAL_ERROR`
- Error reason: `CYCLE_DETECTED`

**Rationale**: Ensures cycle detection (NORP-004) occurs before ordering attempted (NORP-005).

Full test specifications available in `compliance-tests/NORP-005-tests.md`.

---

## 10. Security Considerations

Non-deterministic ordering can be exploited to:
- **Hide malicious behavior** behind timing variance (different orders = different observable side effects)
- **Produce inconsistent audit trails** (compliance violations undetectable)
- **Trigger cost amplification** through unpredictable scheduling (expensive nodes executed redundantly)

Deterministic ordering is therefore part of **workflow integrity** and **operational security**.

---

## 11. Rationale Summary

**Core Principle**: Reproducible execution ordering is mandatory for debugging, auditability, and trust in AI orchestration systems.

Determinism guarantees that identical workflows produce identical execution plans, enabling reliable incident response, compliance audits, and cost attribution.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 12. Future Extensions

Future NORP specifications MAY define:
- Priority-based scheduling with formal semantics
- Adaptive ordering based on runtime metrics (with determinism preserved)
- Distributed ordering guarantees across multi-region deployments
- Ordering preservation under graph mutations

---

## 13. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- Cormen, T. H., et al. (2009). *Introduction to Algorithms* (3rd ed.). MIT Press. (Chapter 22: Topological Sort)
- Kahn, A. B. (1962). "Topological sorting of large networks". *Communications of the ACM*.
- Deterministic systems design principles

---

## 14. Acknowledgments

This specification is derived from topological sorting implementation in NeuraScope BlueprintCompiler (production-tested on 10,000+ workflows).

The authors thank reviewers for feedback on tie-breaking semantics and parallel execution compatibility.

---

## Appendix A: Example Workflows

### A.1 Simple DAG

```json
{
  "name": "Simple Sequential Workflow",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]}
  ]
}
```

**Logical order**: `["A", "B"]` (deterministic, only one valid order)

---

### A.2 Diamond DAG

```json
{
  "name": "Diamond Pattern Workflow",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["A"]},
    {"id": "D", "type": "output", "depends_on": ["B", "C"]}
  ]
}
```

**Logical order** (lexicographic tie-breaking): `["A", "B", "C", "D"]`

**Parallel groups**:
- Level 0: [A]
- Level 1: [B, C] (parallel-eligible)
- Level 2: [D]

---

### A.3 Parallel-Friendly DAG

```json
{
  "name": "ETL Workflow with Parallelism",
  "nodes": [
    {"id": "extract", "type": "datasource", "depends_on": []},
    {"id": "summarize", "type": "llm_call", "depends_on": ["extract"]},
    {"id": "classify", "type": "llm_call", "depends_on": ["extract"]},
    {"id": "publish", "type": "output", "depends_on": ["summarize", "classify"]}
  ]
}
```

**Logical order** (lexicographic): `["extract", "classify", "summarize", "publish"]`

**Parallel groups**:
- Level 0: [extract]
- Level 1: [classify, summarize] (parallel-eligible - both depend only on extract)
- Level 2: [publish]

**Note**: Logical order places "classify" before "summarize" (alphabetical tie-breaking), but physical execution may run them in parallel.

---

## Appendix B: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | 2026-01-09 | Status upgraded to Stable. Added NORP-001 and NORP-004 relationship (3.1), algorithm requirements (5.1.1), deterministic Kahn's pseudocode (5.1.2), tie-breaking examples (5.2.1), logical vs physical order clarification (5.3.1), deterministic diagnostics (Section 6), anti-patterns (8.1), compliance tests (9.1), example workflows (Appendix A). |
| 1.0 | 2026-01-07 | Initial draft. |

---

## Citation

```bibtex
@techreport{norp005-2026,
  title={{NORP-005: Deterministic Topological Ordering for AI Orchestration Workflows}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/NORP-005},
  license={CC BY 4.0}
}
```

---

**NORP-005 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
