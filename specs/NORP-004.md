# NORP-004
## Cycle Detection and Graph Validity for AI Orchestration Systems

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Stable

### Category
Structural Validation

### Version
1.2

### Date
2026-01-09

### Authors
NORP Working Group

---

## 1. Abstract

This specification defines mandatory requirements for detecting and rejecting structural cycles in AI orchestration workflow graphs.

It ensures that workflows form a valid **Directed Acyclic Graph (DAG)** prior to compilation or execution, preventing deadlocks, non-deterministic execution, infinite resource consumption, and unsafe retry behavior.

The objective is to guarantee safe, deterministic, and predictable workflow execution.

---

## 2. Motivation

AI orchestration workflows commonly express execution dependencies as directed graphs.

Structural cycles in such graphs cause:
- **Deadlocks** and infinite execution loops
- **Non-deterministic execution order** (impossible to establish valid sequence)
- **Unbounded resource consumption** (CPU, memory, LLM tokens)
- **Undefined retry and failure semantics** (what to retry if circular dependency?)

Cycle detection MUST therefore occur **before compilation or execution** and MUST reject invalid workflows deterministically.

---

## 3. Scope

This specification applies to systems that execute workflows represented as directed dependency graphs.

### 3.1 Relationship to NORP-001

This specification is an **extension of NORP-001 Section 5.2** (Structural Validation).

- **NORP-001** mandates that structural validation MUST include cycle detection
- **NORP-004** specifies **how cycle detection MUST be implemented**, validated, and diagnosed

A system implementing both NORP-001 and NORP-004 achieves **complete structural safety**.

---

### 3.2 Loops vs Structural Cycles

This specification addresses **structural cycles** in dependency graphs (cyclic edges between nodes).

**Explicit loop constructs** with bounded iteration semantics are **OUT OF SCOPE**.

**Example IN SCOPE** (structural cycle - PROHIBITED):
```
Node A → Node B → Node C → Node A
```

**Example OUT OF SCOPE** (bounded loop construct - may be permitted):
```
Loop(iterations = 10) {
  Node X → Node Y
}
```

Future NORP specifications (e.g., NORP-011) MAY address explicit loop semantics.

---

## 4. Terminology

**Node**: A unit of execution within a workflow.

**Edge**: A directed dependency from one node to another.

**Cycle**: A path in the graph where a node is reachable from itself by following directed edges.

**DAG** (Directed Acyclic Graph): A directed graph with no cycles.

**Back-edge**: An edge pointing to a node currently in the recursion stack (DFS terminology).

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Normative Requirements

### 5.1 Mandatory Acyclic Graph

Workflow graphs MUST be **acyclic** (form a valid DAG).

Any detected cycle MUST result in **immediate rejection** of the workflow.

Cycle tolerance or automatic resolution is **NOT permitted** by default.

---

### 5.2 Validation Timing

Cycle detection MUST occur:
- **Before compilation**
- **Before execution**
- As part of **structural validation** (NORP-001 Stage 1)

Execution of cyclic graphs is **NOT permitted**.

Cycle detection MUST NOT be deferred to runtime.

---

### 5.3 Algorithmic Requirements

Cycle detection MUST operate in **O(V + E)** time or better, where:
- **V** = number of nodes
- **E** = number of edges (dependency relationships)

Algorithms with **super-linear complexity** (e.g., O(V²), O(V³)) are **NOT compliant**.

---

#### 5.3.1 Depth-First Search Algorithm (Reference)

Compliant DFS-based implementations MUST:
- Track **visited nodes** (nodes fully explored)
- Track **recursion stack** (nodes currently being explored)
- Detect **back-edges** (edge to node in recursion stack = cycle)

**Pseudocode**:

```python
def detect_cycle(graph):
    """
    Detect cycles in directed graph using DFS.

    Returns: True if cycle exists, False otherwise
    Complexity: O(V + E)
    """
    visited = set()
    rec_stack = set()

    def dfs(node):
        # Back-edge detected → CYCLE
        if node in rec_stack:
            return True

        # Already fully explored
        if node in visited:
            return False

        # Mark as visiting
        visited.add(node)
        rec_stack.add(node)

        # Explore dependencies
        for dependency in graph[node]:
            if dfs(dependency):
                return True

        # Backtrack (remove from recursion stack)
        rec_stack.remove(node)
        return False

    # Check all nodes (handles disconnected subgraphs)
    for node in graph.all_nodes():
        if dfs(node):
            return True

    return False
```

**Complexity proof**:
- Each node visited **once** → O(V)
- Each edge traversed **once** → O(E)
- **Total**: O(V + E)

---

#### 5.3.2 Alternative: Topological Sort Validation (Optional)

Cycle detection MAY also be performed via **topological sorting** (Kahn's algorithm):

```python
def detect_cycle_kahn(graph):
    """
    Detect cycles via topological sort.

    If all nodes can be sorted, no cycle exists.
    Complexity: O(V + E)
    """
    in_degree = {node: 0 for node in graph}

    # Calculate in-degrees
    for node in graph:
        for dep in graph[node]:
            in_degree[dep] += 1

    # Start with zero in-degree nodes
    queue = [n for n in graph if in_degree[n] == 0]
    sorted_count = 0

    while queue:
        current = queue.pop(0)
        sorted_count += 1

        for dep in graph[current]:
            in_degree[dep] -= 1
            if in_degree[dep] == 0:
                queue.append(dep)

    # If not all nodes sorted → CYCLE exists
    return sorted_count != len(graph)
```

Both DFS and Kahn's are **NORP-004 compliant**.

---

### 5.4 Deterministic Rejection and Diagnostics

When a cycle is detected, the system MUST reject the workflow **deterministically**.

No partial execution plan MAY be produced.

#### 5.4.1 Diagnostic Format

Cycle detection errors MUST include:
- **Error type**: `STRUCTURAL_ERROR`
- **Error code**: `CYCLE_DETECTED`
- **At least one cycle path** (node sequence forming cycle)

**Minimal diagnostic example**:

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "Cycle detected in workflow graph",
  "cycle_path": ["A", "B", "C", "A"]
}
```

Systems MAY include additional information (multiple cycles, graph visualization, fix suggestions).

---

#### 5.4.2 Diagnostic Completeness

Systems SHOULD detect and report **ALL cycles** in the graph (best user experience).

Systems MUST report **at least ONE cycle** (minimum requirement).

**Example complete diagnostic** (recommended):

```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "2 cycles detected in workflow graph",
  "cycles": [
    {"path": ["A", "B", "C", "A"]},
    {"path": ["D", "E", "D"]}
  ]
}
```

---

### 5.5 No Implicit Cycle Breaking

The system MUST NOT automatically:
- Remove edges to break cycles
- Reorder dependencies
- Insert delays, guards, or conditional execution
- Modify the workflow graph structure to bypass cycles

**Cycle resolution is the responsibility of the workflow author.**

Automatic graph transformation is NOT permitted without explicit user consent.

---

## 6. Fail-Safe Behavior

If cycle detection cannot be completed reliably (e.g., algorithm timeout, memory exhaustion), execution MUST be prevented.

Failing to detect cycles MUST be treated as a **validation failure**, not a pass.

---

## 7. Security Considerations

Cyclic graphs may be exploited for:
- **Denial-of-service attacks** (infinite execution consuming resources)
- **Infinite retries** (failed node re-executed indefinitely)
- **Cost amplification** (LLM calls in cycle = unbounded billing)
- **Resource exhaustion** (memory, connections, quotas)

Cycle detection is therefore a **security-critical validation step**.

Strict rejection reduces attack surface and increases system predictability.

---

## 8. Implementation Guidance (Non-Normative)

### 8.1 Common Anti-Patterns

#### Runtime Cycle Detection

❌ **BAD**: Detecting cycles during execution
```python
def execute_node(node):
    if node.execution_count > 100:
        raise Exception("Possible cycle detected after 100 iterations")
    node.execution_count += 1
    # Execute node logic
```

✅ **GOOD**: Detecting cycles before execution
```python
# Validate BEFORE execution
if detect_cycle(workflow.graph):
    raise StructuralError("Cycle detected in graph")

# Then execute (guaranteed acyclic)
execute_workflow(workflow)
```

**Why**: Runtime detection wastes resources, may not detect all cycles, unclear iteration limit.

---

#### Partial Graph Validation

❌ **BAD**: Only validating selected nodes
```javascript
// Only check nodes user modified
for (const node of selectedNodes) {
    if (hasCycle(node)) {
        reject();
    }
}
```

✅ **GOOD**: Validating entire graph
```javascript
// Validate complete workflow graph
if (hasCycle(workflow.allNodes)) {
    reject("Cycle detected in workflow");
}
```

**Why**: Cycle may exist in non-selected subgraph.

---

#### Ignoring Cycle Detection Errors

❌ **BAD**: Continuing execution despite detected cycle
```php
try {
    detectCycles($graph);
} catch (CycleException $e) {
    Log::warning("Cycle detected, proceeding anyway");
    // Continue execution
}
```

✅ **GOOD**: Failing fast on cycle detection
```php
if (detectCycles($graph)) {
    throw new StructuralError("Cycle detected: cannot execute workflow");
}
```

**Why**: Cycles make execution non-deterministic and potentially infinite.

---

### 8.2 Performance Considerations

- Cycle detection on 1,000 nodes typically completes in **<10ms**
- Large graphs (10,000+ nodes) may require **50-100ms**
- Algorithms exceeding these bounds MAY indicate incorrect implementation

---

## 9. Compliance

A system is **NORP-004 compliant** if:
- It rejects all cyclic graphs before compilation/execution
- Cycle detection operates in **O(V + E)** time
- It produces deterministic diagnostics (same workflow = same error)
- It passes all mandatory compliance tests

### 9.1 Compliance Test Suite

**Test 1: Simple Cycle Rejection**
- **Input**: Workflow with cycle `A → B → A`
- **Expected**: Rejection with `STRUCTURAL_ERROR`
- **Diagnostic**: Cycle path `A→B→A`

**Test 2: Multi-Node Cycle Rejection**
- **Input**: Workflow with cycle `A → B → C → A`
- **Expected**: Rejection with `STRUCTURAL_ERROR`
- **Diagnostic**: Cycle path `A→B→C→A`

**Test 3: Self-Loop Rejection**
- **Input**: Workflow with self-loop `A → A`
- **Expected**: Rejection with `STRUCTURAL_ERROR`
- **Diagnostic**: Self-loop detected on node A

**Test 4: Disconnected Subgraph with Cycle**
- **Input**: Valid DAG (A→B) + cycle in disconnected subgraph (C→D→C)
- **Expected**: Rejection with `STRUCTURAL_ERROR`
- **Diagnostic**: Cycle detected in subgraph `C→D→C`
- **Rationale**: Proves entire graph validated, not just connected components

**Test 5: Valid DAG (Negative Test)**
- **Input**: Workflow with no cycles `A → B → C`
- **Expected**: Validation **passes** (no error)
- **Rationale**: Proves no false positives

Full test specifications available in `compliance-tests/NORP-004-tests.md`.

---

## 10. Rationale Summary

**Core Principle**: A workflow with cyclic dependencies cannot be executed deterministically and MUST be rejected.

This invariant is fundamental to safe, predictable orchestration.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 11. Future Extensions

Future NORP specifications MAY define:
- Explicit loop constructs with bounded semantics (NORP-011)
- Iterative execution primitives with formal termination guarantees
- Declarative repetition nodes with cycle-free guarantees
- Conditional cycles with explicit exit conditions

---

## 12. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- Cormen, T. H., et al. (2009). *Introduction to Algorithms* (3rd ed.). MIT Press. (Chapter 22: Elementary Graph Algorithms)
- Directed Acyclic Graph (DAG) theory
- Depth-First Search (DFS) algorithm
- Topological sorting algorithms (Kahn, DFS post-order)

---

## 13. Acknowledgments

This specification is derived from cycle detection implementation in NeuraScope BlueprintValidator (production-tested on 10,000+ workflows).

The authors thank reviewers for feedback on algorithmic requirements and diagnostic formats.

---

## Appendix A: Example Workflows

### A.1 Simple Cycle (INVALID)

```json
{
  "name": "Invalid Workflow - Simple Cycle",
  "nodes": [
    {
      "id": "fetch_data",
      "type": "datasource",
      "depends_on": ["process_data"]
    },
    {
      "id": "process_data",
      "type": "llm_call",
      "depends_on": ["fetch_data"]
    }
  ]
}
```

**Diagnostic**:
```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "message": "Cycle detected in workflow graph",
  "cycle_path": ["fetch_data", "process_data", "fetch_data"]
}
```

---

### A.2 Multi-Node Cycle (INVALID)

```json
{
  "name": "Invalid Workflow - Three-Node Cycle",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": ["C"]},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["B"]}
  ]
}
```

**Diagnostic**:
```json
{
  "error": "STRUCTURAL_ERROR",
  "code": "CYCLE_DETECTED",
  "cycle_path": ["A", "B", "C", "A"]
}
```

---

### A.3 Valid DAG (VALID)

```json
{
  "name": "Valid Workflow - DAG",
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["A"]},
    {"id": "D", "type": "output", "depends_on": ["B", "C"]}
  ]
}
```

**Validation**: ✅ No cycle detected

**Valid execution orders**:
- `[A, B, C, D]`
- `[A, C, B, D]`

Both orders respect dependencies.

---

## Appendix B: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | 2026-01-09 | Status upgraded to Stable. Added DFS algorithm pseudocode (5.3.1), Kahn's alternative (5.3.2), diagnostic format (5.4.1), diagnostic completeness (5.4.2), anti-patterns (8.1), compliance tests (9.1), example workflows (Appendix A), NORP-001 linkage (3.1), loops vs cycles clarification (3.2). |
| 1.0 | 2026-01-07 | Initial draft. |

---

## Citation

```bibtex
@techreport{norp004-2026,
  title={{NORP-004: Cycle Detection and Graph Validity for AI Orchestration Systems}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/NORP-004},
  license={CC BY 4.0}
}
```

---

**NORP-004 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
