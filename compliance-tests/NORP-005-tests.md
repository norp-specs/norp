# NORP-005 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-005 (Deterministic Topological Ordering for AI Orchestration Workflows).

An implementation is **NORP-005 compliant** if and only if it passes **all mandatory tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning workflow orchestration system with topological sorting
- Ability to define workflows with node dependencies
- Access to compilation/ordering API
- Ability to execute same workflow multiple times

### Test Data Format

Workflows should be expressible as:

```json
{
  "nodes": [
    {
      "id": "node_identifier",
      "type": "node_type",
      "depends_on": ["dependency_1", "dependency_2"]
    }
  ]
}
```

---

## Mandatory Tests

### Test 1: Deterministic Order - Simple DAG

**Objective**: Verify that a simple sequential workflow produces deterministic order.

**Input**:

```json
{
  "name": "Test 1 - Simple Sequential Workflow",
  "nodes": [
    {
      "id": "A",
      "type": "datasource",
      "depends_on": []
    },
    {
      "id": "B",
      "type": "llm_call",
      "depends_on": ["A"]
    }
  ]
}
```

**Graph Structure**:
```
A → B
```

**Actions**:
1. Compile workflow → produces ExecutionPlan1
2. Compile same workflow again → produces ExecutionPlan2

**Expected Behavior**:
- ExecutionPlan1.logical_order MUST equal ExecutionPlan2.logical_order
- Both MUST produce: `["A", "B"]`
- Order MUST be identical across runs

**Pass Criteria**:
- ✅ First compilation: `["A", "B"]`
- ✅ Second compilation: `["A", "B"]`
- ✅ ExecutionPlan1 == ExecutionPlan2

**Rationale**: Proves basic determinism on simple dependency chain.

---

### Test 2: Tie-Breaking Consistency

**Objective**: Verify that tie-breaking rules are applied consistently when multiple nodes are equally eligible.

**Input**:

```json
{
  "name": "Test 2 - Tie-Breaking Test",
  "nodes": [
    {
      "id": "Z",
      "type": "llm_call",
      "depends_on": []
    },
    {
      "id": "A",
      "type": "datasource",
      "depends_on": []
    },
    {
      "id": "M",
      "type": "transform",
      "depends_on": []
    }
  ]
}
```

**Graph Structure**:
```
Z (no dependencies)
A (no dependencies)
M (no dependencies)
```

**Tie-Breaking Rule**: Lexicographic ascending by node ID (as documented by system)

**Actions**:
1. Compile workflow → produces order1
2. Compile same workflow again → produces order2

**Expected Behavior**:
- Both compilations MUST produce **identical order**
- If tie-breaking is lexicographic ascending: `["A", "M", "Z"]`

**Pass Criteria**:
- ✅ First compilation: `["A", "M", "Z"]`
- ✅ Second compilation: `["A", "M", "Z"]`
- ✅ Order matches documented tie-breaking rule

**Rationale**: Proves tie-breaking rule is applied consistently when multiple nodes are equally eligible.

**Note**: If system uses different tie-breaking (e.g., insertion order), expected order may differ, but MUST be consistent across runs.

---

### Test 3: Diamond Pattern Determinism

**Objective**: Verify deterministic ordering on workflows with multiple valid topological orders.

**Input**:

```json
{
  "name": "Test 3 - Diamond Pattern",
  "nodes": [
    {
      "id": "A",
      "type": "datasource",
      "depends_on": []
    },
    {
      "id": "B",
      "type": "llm_call",
      "depends_on": ["A"]
    },
    {
      "id": "C",
      "type": "transform",
      "depends_on": ["A"]
    },
    {
      "id": "D",
      "type": "output",
      "depends_on": ["B", "C"]
    }
  ]
}
```

**Graph Structure**:
```
    A
   / \
  B   C
   \ /
    D
```

**Valid topological orders**:
- `["A", "B", "C", "D"]`
- `["A", "C", "B", "D"]`

**Actions**:
1. Compile workflow three times
2. Compare all three logical orders

**Expected Behavior**:
- All three compilations MUST produce **identical logical order**
- If tie-breaking is lexicographic: `["A", "B", "C", "D"]` (B before C alphabetically)
- Order MUST respect dependencies (A before B/C, B/C before D)

**Pass Criteria**:
- ✅ Compilation 1 = Compilation 2 = Compilation 3
- ✅ Dependencies respected (A before B,C; B,C before D)
- ✅ Tie-breaking applied consistently (B vs C ordering stable)

**Rationale**: Proves determinism on graphs with multiple valid orders (diamond pattern).

---

### Test 4: Parallel Eligibility Identification

**Objective**: Verify that system identifies nodes eligible for parallel execution while maintaining logical order determinism.

**Input**: (Same diamond pattern as Test 3)

**Expected Output Structure**:

```json
{
  "logical_order": ["A", "B", "C", "D"],
  "parallel_groups": [
    {
      "level": 0,
      "nodes": ["A"],
      "parallel": false
    },
    {
      "level": 1,
      "nodes": ["B", "C"],
      "parallel": true
    },
    {
      "level": 2,
      "nodes": ["D"],
      "parallel": false
    }
  ]
}
```

**Expected Behavior**:
- **Logical order** is deterministic: `["A", "B", "C", "D"]`
- Nodes **B and C** are identified as **parallel-eligible** (same dependency level, both depend only on A)
- Node **D** is **not eligible** until both B and C complete
- Parallel execution does NOT alter logical order

**Pass Criteria**:
- ✅ Logical order deterministic
- ✅ B and C identified as level 1 (same dependency distance from roots)
- ✅ B and C marked as `parallel: true`
- ✅ D identified as level 2 (depends on level 1 completion)

**Rationale**: Verifies that parallelism optimization does not compromise deterministic ordering guarantees.

**Note**: Systems not supporting parallel execution MAY skip parallel_groups output, but logical_order MUST still be deterministic.

---

### Test 5: Cycle Rejection Cross-Check

**Objective**: Verify that cyclic workflows are rejected **before** ordering is attempted (NORP-004 integration).

**Input**:

```json
{
  "name": "Test 5 - Cycle (Should Reject Before Ordering)",
  "nodes": [
    {
      "id": "A",
      "type": "llm_call",
      "depends_on": ["B"]
    },
    {
      "id": "B",
      "type": "datasource",
      "depends_on": ["A"]
    }
  ]
}
```

**Graph Structure**:
```
A → B → A (cycle)
```

**Expected Behavior**:
- System MUST **reject prior to ordering** (during structural validation)
- Error type: `STRUCTURAL_ERROR`
- Error code: `CYCLE_DETECTED`
- Error reason: Cycle exists in graph

**Pass Criteria**:
- ✅ Rejection occurs at **structural validation** stage (NORP-004)
- ✅ Ordering stage **never reached** (fails fast)
- ✅ Error type = `STRUCTURAL_ERROR`, code = `CYCLE_DETECTED`

**Rationale**: Ensures cycle detection (NORP-004) executes before topological ordering (NORP-005).

---

## Optional Tests (Recommended)

### Test 6: Tie-Breaking with Priority Metadata

**Objective**: Verify that explicit priority metadata is respected in tie-breaking.

**Input**:

```json
{
  "nodes": [
    {"id": "A", "priority": 2, "depends_on": []},
    {"id": "B", "priority": 1, "depends_on": []},
    {"id": "C", "priority": 1, "depends_on": []}
  ]
}
```

**Tie-Breaking Rule**: Ascending priority, then lexicographic ID

**Expected Order**:
- `["B", "C", "A"]` (B and C have priority 1 < A priority 2)
- Within priority 1: `["B", "C"]` (lexicographic)

**Pass Criteria**:
- ✅ Order respects priority: nodes with priority 1 before priority 2
- ✅ Tie within same priority resolved lexicographically
- ✅ Consistent across multiple compilations

---

### Test 7: Large Graph Performance

**Objective**: Verify that ordering operates in O(V + E) time on large graphs.

**Setup**:
- Create DAG with 1,000 nodes
- Create DAG with 10,000 nodes

**Actions**:
1. Measure compilation time for 1,000-node graph → T1
2. Measure compilation time for 10,000-node graph → T2

**Expected Behavior**:
- T1 SHOULD be <50ms
- T2 SHOULD be <500ms
- T2/T1 ratio SHOULD be ~10 (linear scaling)

**Pass Criteria**:
- ✅ Both compilations complete
- ✅ Time scaling approximately linear (proves O(V+E) or O(V log V), not O(V²))

**Rationale**: Proves algorithm scales to production workloads.

---

### Test 8: Ordering Independence from Insertion Order

**Objective**: Verify that logical order is independent of node insertion order in JSON.

**Input A** (nodes in order A, B, C):
```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]},
    {"id": "C", "depends_on": ["A"]}
  ]
}
```

**Input B** (nodes in order C, B, A - reversed):
```json
{
  "nodes": [
    {"id": "C", "depends_on": ["A"]},
    {"id": "B", "depends_on": ["A"]},
    {"id": "A", "depends_on": []}
  ]
}
```

**Expected**:
- Both inputs produce **identical logical order**
- If lexicographic tie-breaking: `["A", "B", "C"]`

**Pass Criteria**:
- ✅ Input A order == Input B order
- ✅ Order independent of JSON insertion sequence

**Rationale**: Ensures ordering is based on dependencies and tie-breaking rules, not input formatting.

---

## Compliance Report Template

```markdown
# NORP-005 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]
**Algorithm**: [Kahn's / DFS post-order / Other]
**Tie-Breaking Rule**: [Lexicographic / Priority / Insertion order]

## Test Results

| Test | Status | Logical Order | Notes |
|------|--------|---------------|-------|
| Test 1: Simple DAG | ✅ Pass | [A, B] | Deterministic across runs |
| Test 2: Tie-Breaking | ✅ Pass | [A, M, Z] | Lexicographic applied |
| Test 3: Diamond Pattern | ✅ Pass | [A, B, C, D] | Consistent across 3 runs |
| Test 4: Parallel Eligibility | ✅ Pass | [A, B, C, D] | B,C identified as parallel |
| Test 5: Cycle Rejection | ✅ Pass | - | Rejected before ordering |

## Compliance Status

✅ **NORP-005 COMPLIANT**

All mandatory tests passed.

## Optional Tests

| Test | Status | Notes |
|------|--------|-------|
| Test 6: Priority Metadata | ✅ Pass | [B, C, A] with priority respected |
| Test 7: Large Graph Performance | ✅ Pass | 1000 nodes in 45ms (O(V log V)) |
| Test 8: Insertion Independence | ✅ Pass | Same order regardless of JSON order |

## Implementation Details

**Tie-Breaking Rule**: Lexicographic ascending by node ID
**Complexity**: O(V log V + E) (Kahn's with sorted queue)
**Parallel Groups**: Yes (level-based grouping)
**Logical vs Physical**: Distinct (logical persisted, physical may be parallel)

## Code Sample

```python
def topological_sort_deterministic(graph):
    in_degree = {node: 0 for node in graph}

    for node in graph:
        for dep in graph[node]:
            in_degree[dep] += 1

    queue = sorted([n for n in graph if in_degree[n] == 0])
    result = []

    while queue:
        current = queue.pop(0)
        result.append(current)

        newly_eligible = []
        for dep in graph[current]:
            in_degree[dep] -= 1
            if in_degree[dep] == 0:
                newly_eligible.append(dep)

        queue = sorted(queue + newly_eligible)  # Deterministic tie-breaking

    if len(result) != len(graph):
        raise CycleDetectedError()

    return result
```

## Performance Benchmarks

| Graph Size | Compilation Time | Complexity Verified |
|------------|------------------|---------------------|
| 10 nodes | <1ms | O(V log V) |
| 100 nodes | ~8ms | O(V log V) |
| 1,000 nodes | ~85ms | O(V log V) |
| 10,000 nodes | ~950ms | O(V log V) |

**Note**: O(V log V + E) due to sorting in tie-breaking (acceptable for production).
```

---

## Certification

To claim **NORP-005 Certification**:
1. Pass all 5 mandatory tests
2. Document tie-breaking rule used
3. Verify determinism across at least 3 compilations (Test 3)
4. Publish compliance report
5. Submit to NORP registry (norp@neurascope.ai)

---

## Edge Cases and Additional Validation

### Edge Case 1: Empty Workflow

**Input**:
```json
{"nodes": []}
```

**Expected**: Empty logical order `[]` (deterministic)

---

### Edge Case 2: Single Node (No Dependencies)

**Input**:
```json
{
  "nodes": [
    {"id": "A", "depends_on": []}
  ]
}
```

**Expected**: Logical order `["A"]` (deterministic)

---

### Edge Case 3: Disconnected Subgraphs

**Input**:
```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]},
    {"id": "C", "depends_on": []},
    {"id": "D", "depends_on": ["C"]}
  ]
}
```

**Graph Structure**:
```
A → B (subgraph 1)
C → D (subgraph 2, disconnected)
```

**Expected**:
- Both subgraphs ordered deterministically
- If lexicographic: `["A", "B", "C", "D"]` or `["C", "D", "A", "B"]` (consistently)
- Tie-breaking applies across subgraphs

**Pass Criteria**:
- ✅ Order deterministic
- ✅ Both subgraphs included in order

---

### Edge Case 4: Complex Diamond (Multiple Levels)

**Input**:

```json
{
  "nodes": [
    {"id": "A", "depends_on": []},
    {"id": "B", "depends_on": ["A"]},
    {"id": "C", "depends_on": ["A"]},
    {"id": "D", "depends_on": ["A"]},
    {"id": "E", "depends_on": ["B", "C", "D"]}
  ]
}
```

**Graph Structure**:
```
      A
    / | \
   B  C  D
    \ | /
      E
```

**Expected**:
- Logical order respects dependencies (A before B/C/D, all before E)
- Tie-breaking determines B, C, D order
- If lexicographic: `["A", "B", "C", "D", "E"]`

**Pass Criteria**:
- ✅ Dependencies respected
- ✅ B, C, D ordered deterministically
- ✅ Consistent across runs

---

## Debugging Failed Tests

### If Test 1 fails (non-deterministic simple DAG)

**Possible causes**:
- Tie-breaking not implemented
- Using non-deterministic data structures (unordered sets/maps)
- Runtime timing influencing order

**Fix**: Implement deterministic tie-breaking (Section 5.2).

---

### If Test 2 fails (tie-breaking inconsistent)

**Possible causes**:
- Tie-breaking rule not documented
- Using hash-based iteration (Python <3.7, JavaScript objects)
- Random or timestamp-based ordering

**Fix**: Use stable sorting (lexicographic, explicit priority, or insertion index).

---

### If Test 3 fails (diamond pattern non-deterministic)

**Possible causes**:
- Partial ordering (not handling all nodes at same level)
- Non-stable tie-breaking across runs

**Fix**: Ensure tie-breaking is applied at **every decision point** where multiple nodes are eligible.

---

### If Test 4 fails (parallel groups incorrect)

**Possible causes**:
- Dependency level calculation incorrect
- Not identifying nodes at same distance from roots

**Fix**: Implement BFS-based level detection or equivalent.

---

### If Test 5 fails (cycle not rejected before ordering)

**Possible causes**:
- Cycle detection not implemented (NORP-004 non-compliant)
- Ordering attempted before validation

**Fix**: Implement NORP-004 cycle detection **before** NORP-005 ordering.

---

## Interoperability Testing

### Cross-System Ordering Comparison

To verify interoperability between two NORP-005 compliant systems:

1. Define same workflow W
2. Configure both systems with **same tie-breaking rule** (e.g., lexicographic)
3. Compile W on System A → Order A
4. Compile W on System B → Order B

**Expected**: Order A == Order B (both NORP-005 compliant systems produce identical order)

**If orders differ**: One or both systems are NOT truly NORP-005 compliant.

---

**NORP-005 Compliance Tests v1.2**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
