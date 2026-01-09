# NORP-004 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-004 (Cycle Detection and Graph Validity).

An implementation is **NORP-004 compliant** if and only if it passes **all mandatory tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning workflow orchestration system with graph-based workflows
- Ability to define workflows with node dependencies
- Access to validation API or cycle detection function

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

### Test 1: Simple Cycle Rejection

**Objective**: Verify that a simple two-node cycle is detected and rejected.

**Input**:

```json
{
  "name": "Test 1 - Simple Cycle",
  "nodes": [
    {
      "id": "A",
      "type": "datasource",
      "depends_on": ["B"]
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
A → B → A (cycle)
```

**Expected Behavior**:
- System MUST **reject** during Structural Validation (before compilation/execution)
- System MUST return error type: `STRUCTURAL_ERROR`
- Error code MUST be: `CYCLE_DETECTED`
- Error diagnostic MUST include cycle path: `["A", "B", "A"]` or equivalent

**Pass Criteria**:
- ✅ Workflow rejected
- ✅ Error type = `STRUCTURAL_ERROR`
- ✅ Error code = `CYCLE_DETECTED`
- ✅ Cycle path identified
- ✅ Rejection occurs BEFORE resource loading

**Example Expected Error**:

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "Cycle detected in workflow graph",
  "cycle_path": ["A", "B", "A"]
}
```

---

### Test 2: Multi-Node Cycle Rejection

**Objective**: Verify that longer cycles (3+ nodes) are detected.

**Input**:

```json
{
  "name": "Test 2 - Three-Node Cycle",
  "nodes": [
    {
      "id": "A",
      "type": "datasource",
      "depends_on": ["C"]
    },
    {
      "id": "B",
      "type": "llm_call",
      "depends_on": ["A"]
    },
    {
      "id": "C",
      "type": "transform",
      "depends_on": ["B"]
    }
  ]
}
```

**Graph Structure**:
```
A → B → C → A (3-node cycle)
```

**Expected Behavior**:
- System MUST reject with `STRUCTURAL_ERROR`
- Cycle path MUST include: `["A", "B", "C", "A"]` or equivalent

**Pass Criteria**:
- ✅ Workflow rejected
- ✅ 3-node cycle correctly identified
- ✅ Complete cycle path returned

---

### Test 3: Self-Loop Rejection

**Objective**: Verify that self-referential nodes (node depending on itself) are detected.

**Input**:

```json
{
  "name": "Test 3 - Self Loop",
  "nodes": [
    {
      "id": "A",
      "type": "llm_call",
      "depends_on": ["A"]
    }
  ]
}
```

**Graph Structure**:
```
A → A (self-loop)
```

**Expected Behavior**:
- System MUST reject with `STRUCTURAL_ERROR`
- Error diagnostic SHOULD indicate self-loop: `["A", "A"]` or "Self-loop on node A"

**Pass Criteria**:
- ✅ Workflow rejected
- ✅ Self-loop detected
- ✅ Error clearly indicates self-referential dependency

---

### Test 4: Disconnected Subgraph with Cycle

**Objective**: Verify that cycles in disconnected subgraphs are detected (not just main graph).

**Input**:

```json
{
  "name": "Test 4 - Disconnected Subgraph Cycle",
  "nodes": [
    {
      "id": "A",
      "type": "datasource",
      "depends_on": []
    },
    {
      "id": "B",
      "type": "llm_call",
      "depends_on": []
    },
    {
      "id": "C",
      "type": "transform",
      "depends_on": ["D"]
    },
    {
      "id": "D",
      "type": "output",
      "depends_on": ["C"]
    }
  ]
}
```

**Graph Structure**:
```
A (standalone)
B (standalone)
C → D → C (cycle in disconnected subgraph)
```

**Expected Behavior**:
- System MUST reject entire workflow (not just subgraph)
- System MUST detect cycle in C→D subgraph
- Error diagnostic MUST include: `["C", "D", "C"]`

**Pass Criteria**:
- ✅ Workflow rejected despite A and B being valid
- ✅ Cycle in disconnected subgraph detected
- ✅ Proves entire graph validated (not just connected components)

**Rationale**: Some naive implementations only validate the "main" execution path, missing disconnected cycles.

---

### Test 5: Valid DAG (Negative Test)

**Objective**: Verify that valid acyclic graphs are NOT rejected (no false positives).

**Input**:

```json
{
  "name": "Test 5 - Valid DAG",
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
A → B → D
A → C → D
(Diamond pattern, no cycles)
```

**Expected Behavior**:
- System MUST **accept** workflow (validation passes)
- No `CYCLE_DETECTED` error
- Workflow proceeds to compilation stage

**Valid Execution Orders** (examples):
- `[A, B, C, D]`
- `[A, C, B, D]`

**Pass Criteria**:
- ✅ Validation succeeds (no errors)
- ✅ Workflow proceeds to next stage
- ✅ No false positive cycle detection

**Rationale**: Ensures cycle detection is precise (no false alarms on valid DAGs).

---

## Optional Tests (Recommended)

### Test 6: Complex Multi-Cycle Graph

**Objective**: Verify that systems can detect multiple independent cycles.

**Input**:

```json
{
  "nodes": [
    {"id": "A", "depends_on": ["B"]},
    {"id": "B", "depends_on": ["A"]},
    {"id": "C", "depends_on": ["D"]},
    {"id": "D", "depends_on": ["C"]}
  ]
}
```

**Graph Structure**:
```
A ↔ B (cycle 1)
C ↔ D (cycle 2)
```

**Expected Behavior** (SHOULD):
- System SHOULD detect both cycles
- Diagnostic SHOULD include: `[["A", "B", "A"], ["C", "D", "C"]]`

**Expected Behavior** (MUST):
- System MUST detect at least ONE cycle
- Workflow MUST be rejected

**Pass Criteria**:
- ✅ Workflow rejected
- ⚠️ Bonus: Both cycles identified

---

### Test 7: Performance Validation (O(V+E))

**Objective**: Verify that cycle detection operates in linear time.

**Setup**:
- Create large DAG with 1,000 nodes
- Create similar graph with 1,000 nodes + 1 cycle

**Actions**:
1. Measure time to validate 1,000-node DAG → T1
2. Measure time to validate 1,000-node graph with cycle → T2

**Expected Behavior**:
- T1 and T2 SHOULD be comparable (both ~10-50ms)
- Neither should exceed O(V²) behavior (e.g., >500ms would indicate O(V²))

**Pass Criteria**:
- ✅ Both validations complete in <100ms (proves O(V+E), not O(V²))
- ✅ Cycle detected even in large graph

**Rationale**: Proves scalability of cycle detection algorithm.

---

## Compliance Report Template

```markdown
# NORP-004 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]
**Algorithm Used**: [DFS / Kahn's / Other]

## Test Results

| Test | Status | Time | Notes |
|------|--------|------|-------|
| Test 1: Simple Cycle | ✅ Pass | 2ms | Cycle A→B→A detected |
| Test 2: Multi-Node Cycle | ✅ Pass | 3ms | Cycle A→B→C→A detected |
| Test 3: Self-Loop | ✅ Pass | 1ms | Self-loop on A detected |
| Test 4: Disconnected Subgraph | ✅ Pass | 4ms | Cycle C→D→C in subgraph detected |
| Test 5: Valid DAG | ✅ Pass | 5ms | No false positive |
| Test 6: Multi-Cycle (optional) | ✅ Pass | 6ms | Both cycles detected |
| Test 7: Performance (optional) | ✅ Pass | 12ms for 1000 nodes | O(V+E) confirmed |

## Compliance Status

✅ **NORP-004 COMPLIANT**

All mandatory tests passed.

## Implementation Details

**Algorithm**: Depth-First Search with recursion stack
**Complexity**: O(V + E) verified
**Diagnostic Format**: JSON with cycle_path array
**Completeness**: Reports all cycles (not just first)

## Code Sample

```python
def detect_cycle_dfs(graph):
    visited = set()
    stack = set()

    def dfs(node):
        if node in stack:
            return True
        if node in visited:
            return False

        visited.add(node)
        stack.add(node)

        for dep in graph.get(node, []):
            if dfs(dep):
                return True

        stack.remove(node)
        return False

    for node in graph:
        if dfs(node):
            return True
    return False
```

## Performance Benchmarks

| Graph Size | Validation Time | Complexity |
|------------|-----------------|------------|
| 10 nodes | <1ms | O(V+E) |
| 100 nodes | ~5ms | O(V+E) |
| 1,000 nodes | ~50ms | O(V+E) |
| 10,000 nodes | ~500ms | O(V+E) |
```

---

## Certification

To claim **NORP-004 Certification**:
1. Pass all 5 mandatory tests
2. Document cycle detection algorithm used
3. Verify O(V + E) complexity (optional Test 7 recommended)
4. Publish compliance report
5. Submit to NORP registry (norp@neurascope.ai)

---

## Additional Validation

### Edge Cases to Consider

**Empty Graph**:
```json
{"nodes": []}
```
Expected: Validation passes (no cycles in empty graph)

**Single Node (no dependencies)**:
```json
{"nodes": [{"id": "A", "depends_on": []}]}
```
Expected: Validation passes

**Non-existent Dependency**:
```json
{
  "nodes": [
    {"id": "A", "depends_on": ["B"]}
  ]
}
```
Expected: Error (missing node B), but NOT `CYCLE_DETECTED` (different error type)

---

**NORP-004 Compliance Tests v1.2**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
