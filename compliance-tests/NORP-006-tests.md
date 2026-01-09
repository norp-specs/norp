# NORP-006 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-006 (Execution Context Isolation and Resource Lifetime Management).

An implementation is **NORP-006 compliant** if and only if it passes **all mandatory tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning orchestration system with execution context support
- Ability to allocate external resources (database connections, API clients)
- Ability to execute same workflow multiple times
- Access to resource allocation/disposal logs

### Test Data

**Workflow with Multiple Resource Access**:

```json
{
  "name": "Multi-Resource Workflow",
  "nodes": [
    {
      "id": "node1",
      "type": "datasource",
      "config": {"connection_id": 5}
    },
    {
      "id": "node2",
      "type": "llm_call",
      "config": {"connection_id": 5}
    },
    {
      "id": "node3",
      "type": "datasource",
      "config": {"connection_id": 5}
    }
  ]
}
```

All 3 nodes access same resource (connection_id 5).

---

## Mandatory Tests

### Test 1: Execution Isolation

**Objective**: Verify that two executions have distinct, isolated resource instances.

**Setup**:
- Define workflow W
- Execute W with execution_id = "exec_1" → allocates resources
- Execute W with execution_id = "exec_2" → allocates resources

**Actions**:
1. Monitor resource allocation for exec_1
2. Monitor resource allocation for exec_2
3. Compare resource handles/instances

**Expected Behavior**:
- exec_1 creates resource instance R1
- exec_2 creates **different** resource instance R2
- R1 ≠ R2 (distinct handles, not shared)

**Pass Criteria**:
- ✅ Two distinct resource instances allocated
- ✅ Resources tagged with different execution_ids
- ✅ No sharing between executions

**Verification Method**:
Check logs for resource allocation events:
```json
{"execution_id": "exec_1", "resource_handle": "conn_abc"}
{"execution_id": "exec_2", "resource_handle": "conn_xyz"}
```

`conn_abc` ≠ `conn_xyz` → Pass

---

### Test 2: Intra-Execution Pooling

**Objective**: Verify that multiple nodes within same execution can reuse same resource (performance optimization).

**Input**: Workflow with 3 nodes all accessing database connection_id = 5

**Setup**:
- Single execution with execution_id = "exec_123"

**Actions**:
1. Execute workflow
2. Monitor database connection allocation
3. Count how many connections created

**Expected Behavior**:
- **Only 1 connection** created (not 3)
- All 3 nodes use **same connection instance**
- Connection lifetime = execution lifetime

**Pass Criteria**:
- ✅ Connection count = 1 (pooling works)
- ✅ All 3 nodes reference same connection handle
- ✅ Connection created once, reused 2 additional times

**Verification Method**:
```
Logs:
[exec_123] node1: get_resource(database, 5) → conn_abc (CREATED)
[exec_123] node2: get_resource(database, 5) → conn_abc (REUSED)
[exec_123] node3: get_resource(database, 5) → conn_abc (REUSED)
```

---

### Test 3: Cross-Execution Reuse Rejection

**Objective**: Verify that resources from completed executions are NOT reused by new executions.

**Setup**:
- Execute workflow W1 (execution_id = "exec_1") → creates resource R1
- W1 completes successfully
- Execute workflow W2 (execution_id = "exec_2", same tenant, same workflow)

**Actions**:
1. Verify resource R1 disposed after W1
2. Monitor resource allocation for W2
3. Verify W2 creates NEW resource R2

**Expected Behavior**:
- R1 disposed at W1 termination
- W2 creates **new resource R2** (not reusing R1)
- R1 ≠ R2

**Pass Criteria**:
- ✅ R1 cleanup log entry exists after W1
- ✅ W2 allocates new resource R2
- ✅ R2 handle ≠ R1 handle

**Verification Method**:
```
[exec_1] Execution completed → cleanup()
[exec_1] Resource disposed: database:5 (conn_abc)
[exec_2] Execution started
[exec_2] Resource allocated: database:5 (conn_xyz)  ← NEW handle
```

---

### Test 4: Failure Cleanup

**Objective**: Verify that resources are disposed even if execution fails.

**Setup**:
- Workflow with 2 nodes
- Node 1 allocates database connection
- Node 2 throws exception (simulated failure)

**Actions**:
1. Execute workflow → Node 1 succeeds, Node 2 fails
2. Verify cleanup triggered despite failure

**Expected Behavior**:
- Execution fails at Node 2
- Database connection from Node 1 MUST be **closed**
- Cleanup occurs in `finally` block (or equivalent)

**Pass Criteria**:
- ✅ Execution status = FAILED
- ✅ Resource disposal log entry exists
- ✅ Database server shows connection closed

**Verification Method**:
```sql
-- Query database server for active connections
SELECT * FROM information_schema.processlist WHERE user = 'workflow_user';
-- Should show 0 connections after cleanup
```

**Rationale**: Proves no resource leaks on failure.

---

### Test 5: Tenant + Execution Isolation (Double Isolation)

**Objective**: Verify that resources are isolated by BOTH tenant_id (NORP-002) AND execution_id (NORP-006).

**Setup**:
- Tenant A executes workflow → Execution 1
- Tenant A executes same workflow → Execution 2 (same tenant, different execution)

**Actions**:
1. Monitor resource allocation for both executions
2. Verify resources scoped by tenant_id + execution_id

**Expected Behavior**:
- Resources tagged with **both identifiers**
- Execution 1 resources ≠ Execution 2 resources (despite same tenant)

**Pass Criteria**:
- ✅ Resource keys include both tenant_id and execution_id
- ✅ No cross-execution sharing within same tenant

**Example Resource Key**:
```
tenant:acme:exec:exec_1:database:5
tenant:acme:exec:exec_2:database:5
```

Different execution_ids → different resources.

**Rationale**: Proves NORP-002 (tenant) and NORP-006 (execution) work together.

---

## Optional Tests (Recommended)

### Test 6: Lazy vs Eager Loading

**Objective**: Verify that lazy loading and eager loading both enforce execution scoping.

**Lazy Loading** (resource created on first use):
- Workflow node accesses resource → allocated
- Resource tagged with execution_id

**Eager Loading** (resources pre-loaded at context creation):
- Context created → all resources allocated immediately
- All tagged with execution_id

**Pass Criteria**:
- ✅ Both approaches produce execution-scoped resources
- ✅ Cleanup works for both

---

### Test 7: Concurrent Execution Isolation

**Objective**: Verify that concurrent executions (parallel) do not share resources.

**Setup**:
- Execute workflow W1 and W2 **simultaneously** (parallel executions)

**Expected**:
- W1 and W2 have distinct execution_ids
- Resources are NOT shared (even though concurrent)

**Pass Criteria**:
- ✅ W1 resources ≠ W2 resources
- ✅ No race conditions or shared state

---

## Compliance Report Template

```markdown
# NORP-006 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]
**Execution ID Generation**: [UUID v4 / Timestamp-based / Other]

## Test Results

| Test | Status | Notes |
|------|--------|-------|
| Test 1: Execution Isolation | ✅ Pass | Distinct resources per execution |
| Test 2: Intra-Execution Pooling | ✅ Pass | 1 connection reused 3 times |
| Test 3: Cross-Execution Reuse | ✅ Pass | No reuse detected |
| Test 4: Failure Cleanup | ✅ Pass | Cleanup in finally block |
| Test 5: Tenant + Execution | ✅ Pass | Double isolation verified |

## Compliance Status

✅ **NORP-006 COMPLIANT**

All mandatory tests passed.

## Implementation Details

**Execution Context Pattern**: Custom ExecutionContext class
**Resource Pooling**: Lazy loading with dict cache
**Cleanup Mechanism**: try-finally block
**Scoping Keys**: `tenant:{tenant_id}:exec:{exec_id}:resource:{id}`

## Code Sample

```python
class ExecutionContext:
    def __init__(self, execution_id, tenant_id):
        self.execution_id = execution_id
        self.tenant_id = tenant_id
        self._resources = {}

    def get_resource(self, type, id):
        key = f"{type}:{id}"
        if key not in self._resources:
            self._resources[key] = load_resource(type, id, self.tenant_id)
        return self._resources[key]

    def cleanup(self):
        for res in self._resources.values():
            res.close()
        self._resources.clear()
```

## Performance Impact

- **Without pooling** (1 connection per node): 3 connections × 100ms = 300ms overhead
- **With pooling** (NORP-006 compliant): 1 connection × 100ms = 100ms overhead
- **Savings**: 200ms per workflow execution
```

---

## Certification

To claim **NORP-006 Certification**:
1. Pass all 5 mandatory tests
2. Document execution_id generation method
3. Provide ExecutionContext implementation or equivalent
4. Demonstrate cleanup on failure (Test 4)
5. Publish compliance report
6. Submit to NORP registry (norp@neurascope.ai)

---

**NORP-006 Compliance Tests v1.2**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
