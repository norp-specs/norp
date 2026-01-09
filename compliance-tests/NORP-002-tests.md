# NORP-002 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-002 (Multi-Tenant Resource Isolation).

An implementation is **NORP-002 compliant** if and only if it passes **all mandatory tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning multi-tenant orchestration system
- At least **2 distinct tenants** configured (e.g., Tenant A and Tenant B)
- Resources owned by each tenant
- At least **1 global resource** (if global resources supported)

### Test Data

**Tenant A**: `tenant_id = "acme"`
- Resource: DataSource ID 1 (MySQL database)
- Resource: LLM Server ID 1 (OpenAI GPT-4)
- User: `user_a@acme.com`

**Tenant B**: `tenant_id = "globex"`
- Resource: DataSource ID 2 (PostgreSQL database)
- Resource: LLM Server ID 2 (Anthropic Claude)
- User: `user_b@globex.com`

**Global Resource**: LLM Server ID 99 (`is_global = true`, Mistral API)

---

## Mandatory Tests

### Test 1: Tenant Conflict Rejection

**Objective**: Verify that conflicting tenant identities result in execution rejection.

**Setup**:
- Create workflow W owned by Tenant A
- Authenticate as Tenant B (different tenant)
- Attempt to execute workflow W

**Input**:
```json
{
  "workflow_id": "W",
  "created_by_tenant_id": "acme"
}
```

**Execution Context**:
```http
Authorization: Bearer {tenant_b_api_key}
X-Tenant-ID: globex
```

**Expected Behavior**:
- System MUST **reject execution**
- System MUST return error type: `PERMISSION_ERROR`
- Error message SHOULD indicate: "Tenant conflict: workflow owned by acme, execution context is globex"

**Pass Criteria**:
- ✅ Execution rejected
- ✅ Error type = `PERMISSION_ERROR`
- ✅ Rejection occurs during tenant resolution (before resource loading)

---

### Test 2: Cross-Tenant Resource Rejection

**Objective**: Verify that workflows cannot reference resources owned by other tenants.

**Setup**:
- Authenticate as Tenant A
- Create workflow referencing DataSource ID 2 (owned by Tenant B)

**Input**:
```json
{
  "nodes": [
    {
      "id": "fetch_data",
      "type": "datasource",
      "config": {
        "connection_id": 2
      }
    }
  ]
}
```

**Execution Context**: Tenant A

**Expected Behavior**:
- System MUST **reject during Context Resolution** stage
- System MUST return error type: `RESOURCE_ERROR` or `PERMISSION_ERROR`
- Error message SHOULD indicate: "DataSource 2 not accessible by tenant acme"

**Pass Criteria**:
- ✅ Rejection occurs at Context Resolution (before execution)
- ✅ Error type = `RESOURCE_ERROR` or `PERMISSION_ERROR`
- ✅ DataSource 2 is NOT loaded or connected

---

### Test 3: Global Resource Access

**Objective**: Verify that global resources are accessible by all tenants.

**Setup**:
- LLM Server ID 99 marked as global (`is_global = true`)
- Authenticate as Tenant A

**Input**:
```json
{
  "nodes": [
    {
      "id": "llm_call",
      "type": "llm_call",
      "config": {
        "llm_server_id": 99,
        "model": "mistral-large",
        "prompt": "Hello world"
      }
    }
  ]
}
```

**Expected Behavior**:
- Workflow MUST **pass validation**
- Context Resolution MUST allow access to LLM Server 99
- Execution MUST succeed
- Request to LLM Server 99 MUST be **logged with tenant identifier** (audit trail)

**Pass Criteria**:
- ✅ Validation succeeds
- ✅ Execution succeeds
- ✅ Audit log contains: `tenant_id = acme, resource_id = 99`

---

### Test 4: Runtime Access Escalation Attempt

**Objective**: Verify that runtime code cannot access resources outside validated scope.

**Setup**:
- Workflow validated with DataSource ID 1 (Tenant A)
- During execution, node attempts to access DataSource ID 2 (Tenant B)

**Input**:
```json
{
  "nodes": [
    {
      "id": "malicious_node",
      "type": "custom_code",
      "config": {
        "code": "ctx.datasources.get(2).query('SELECT * FROM secrets')"
      }
    }
  ]
}
```

**Execution Context**: Tenant A

**Expected Behavior**:
- Execution MUST **fail immediately** when attempting to access DataSource 2
- System MUST return error type: `PERMISSION_ERROR`
- DataSource 2 MUST NOT be accessed (verify via database logs)

**Pass Criteria**:
- ✅ Execution fails at runtime escalation attempt
- ✅ Error type = `PERMISSION_ERROR`
- ✅ No queries executed against DataSource 2 (verified via audit logs)

**Note**: If system does not support custom code execution, this test MAY be skipped with documented justification.

---

### Test 5: Side Effect Isolation

**Objective**: Verify that workflow execution cannot write to other tenants' storage.

**Setup**:
- Authenticate as Tenant A
- Workflow contains write operation

**Input**:
```json
{
  "nodes": [
    {
      "id": "write_results",
      "type": "datasource",
      "config": {
        "connection_id": 1,
        "query": "INSERT INTO results (data, tenant_id) VALUES ('test', 'acme')"
      }
    }
  ]
}
```

**Expected Behavior**:
- Workflow executes successfully
- Write operation completes
- **Verification**: Query Tenant B database → ZERO new rows
- **Verification**: Query Tenant A database → 1 new row with `tenant_id = 'acme'`

**Pass Criteria**:
- ✅ Execution succeeds
- ✅ Tenant A database has new row
- ✅ Tenant B database unchanged (side effect isolation verified)

---

## Optional Tests (Recommended)

### Test 6: Implicit Access Denial

**Objective**: Verify that absence of tenant filter results in zero results (not global access).

**Setup**:
- Query resources without specifying tenant
- System uses implicit tenant from authentication

**Input**:
```python
# Code WITHOUT explicit tenant filter
resources = db.query("SELECT * FROM resources WHERE id = 123")
```

**Expected Behavior**:
- System SHOULD inject tenant filter automatically (via ORM scope or middleware)
- Results SHOULD be limited to authenticated tenant
- If no automatic injection: Query SHOULD return empty set (fail-safe)

**Pass Criteria**:
- ✅ Results limited to authenticated tenant
- ✅ OR empty set if tenant cannot be inferred

---

### Test 7: Tenant Switching During Execution

**Objective**: Verify that tenant context cannot change mid-execution.

**Setup**:
- Start execution with Tenant A
- During execution, attempt to switch context to Tenant B

**Expected Behavior**:
- Context switch MUST be rejected
- Execution continues with original tenant (A)
- OR execution aborts with error

**Pass Criteria**:
- ✅ Tenant context immutable during execution
- ✅ Switch attempt logged as security event

---

## Compliance Report Template

```markdown
# NORP-002 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]
**Tenant Model**: [Organization-level / User-level / Hierarchical]

## Test Results

| Test | Status | Notes |
|------|--------|-------|
| Test 1: Conflict Rejection | ✅ Pass | |
| Test 2: Cross-Tenant Resource | ✅ Pass | |
| Test 3: Global Resource | ✅ Pass | |
| Test 4: Runtime Escalation | ✅ Pass | |
| Test 5: Side Effect Isolation | ✅ Pass | |

## Compliance Status

✅ **NORP-002 COMPLIANT**

All mandatory tests passed.

## Optional Features

- [x] Cross-tenant collaboration (5.6.1)
- [ ] Implicit tenant injection (Test 6)
- [x] Immutable execution context (Test 7)

## Isolation Verification

COUNT test results (Section 5.2.2):
- WITH filter: 15 resources (Tenant A)
- WITHOUT filter: 1547 resources (all tenants)
- Isolation verified: 1547 > 15 ✅
```

---

## Certification

To claim **NORP-002 Certification**:
1. Pass all 5 mandatory tests
2. Publish compliance report
3. Document tenant model explicitly
4. Submit to NORP registry (norp@neurascope.ai)

---

**NORP-002 Compliance Tests v1.2**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
