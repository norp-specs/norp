# NORP-001 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-001 (Pre-Execution Validation Pipeline).

An implementation is **NORP-001 compliant** if and only if it passes **all tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning workflow orchestration system
- Ability to define workflows programmatically or via JSON/YAML
- Access to validation and execution APIs

### Test Data

Sample workflow definitions are provided in `../examples/`.

---

## Mandatory Tests

### Test 1: Cycle Rejection

**Objective**: Verify that workflows containing cycles are rejected during Structural Validation.

**Input**:
```json
{
  "nodes": [
    {"id": "A", "type": "llm_call", "depends_on": ["C"]},
    {"id": "B", "type": "database_query", "depends_on": ["A"]},
    {"id": "C", "type": "transform", "depends_on": ["B"]}
  ]
}
```

**Expected Behavior**:
- System MUST reject workflow during **Structural Validation** stage
- System MUST return error type: `STRUCTURAL_ERROR`
- Error message SHOULD include cycle path (e.g., "Cycle detected: A→B→C→A")

**Pass Criteria**:
- ✅ Workflow rejected before any resource loading
- ✅ Error type matches `STRUCTURAL_ERROR`
- ✅ Cycle is correctly identified

---

### Test 2: Deterministic Execution Order

**Objective**: Verify that compilation produces deterministic execution order.

**Input**:
```json
{
  "nodes": [
    {"id": "A", "type": "datasource", "depends_on": []},
    {"id": "B", "type": "llm_call", "depends_on": ["A"]},
    {"id": "C", "type": "llm_call", "depends_on": ["A"]}
  ]
}
```

**Expected Behavior**:
- Validate and compile the workflow **twice**
- Execution order MUST be **identical** both times
- Valid orders: `[A, B, C]` or `[A, C, B]` (both respect dependencies)
- The system MUST consistently choose one

**Pass Criteria**:
- ✅ First compilation produces order O1
- ✅ Second compilation produces order O2
- ✅ O1 == O2

---

### Test 3: Missing Resource Rejection

**Objective**: Verify that workflows referencing non-existent resources are rejected during Context Resolution.

**Input**:
```json
{
  "nodes": [
    {
      "id": "A",
      "type": "llm_call",
      "config": {
        "llm_server_id": 99999
      },
      "depends_on": []
    }
  ]
}
```

**Expected Behavior**:
- System MUST pass Structural Validation (graph is valid)
- System MUST reject during **Context Resolution** stage
- System MUST return error type: `RESOURCE_ERROR`
- Error message SHOULD identify missing resource (e.g., "LLM server 99999 not found")

**Pass Criteria**:
- ✅ Rejection occurs at Context Resolution stage
- ✅ Error type matches `RESOURCE_ERROR`
- ✅ Missing resource is identified

---

### Test 4: Fail-Fast Validation Precedence

**Objective**: Verify that validation stages execute in strict order (early stages fail before late stages run).

**Input**:
```json
{
  "nodes": [
    {"id": "A", "type": "llm_call", "depends_on": ["B"], "config": {"llm_server_id": 99999}},
    {"id": "B", "type": "database_query", "depends_on": ["A"]}
  ]
}
```

**Properties**:
- Contains a **cycle** (A→B→A) → Should fail at Structural Validation
- References **non-existent resource** (LLM 99999) → Would fail at Context Resolution

**Expected Behavior**:
- System MUST reject at **Structural Validation** stage (before checking resources)
- System MUST return error type: `STRUCTURAL_ERROR` (NOT `RESOURCE_ERROR`)

**Pass Criteria**:
- ✅ Rejection occurs at Structural Validation
- ✅ Error type is `STRUCTURAL_ERROR`
- ✅ Resource check never executed (can be verified via logs)

---

### Test 5: Permission Check

**Objective**: Verify that workflows referencing resources from other tenants are rejected.

**Setup**:
- Create resource R owned by Tenant X
- Authenticate as Tenant Y
- Attempt to execute workflow referencing resource R

**Input**:
```json
{
  "nodes": [
    {
      "id": "A",
      "type": "datasource",
      "config": {
        "connection_id": R
      },
      "depends_on": []
    }
  ]
}
```

**Expected Behavior**:
- System MUST reject during **Context Resolution** stage
- System MUST return error type: `PERMISSION_ERROR`
- Error message SHOULD indicate access denial

**Pass Criteria**:
- ✅ Rejection occurs at Context Resolution
- ✅ Error type matches `PERMISSION_ERROR`
- ✅ Resource is NOT loaded or accessed

---

### Test 6: Valid Workflow Execution

**Objective**: Verify that a valid workflow passes all validation stages and executes successfully.

**Input**:
```json
{
  "nodes": [
    {"id": "A", "type": "datasource", "config": {"connection_id": 1}, "depends_on": []},
    {"id": "B", "type": "llm_call", "config": {"llm_server_id": 1}, "depends_on": ["A"]}
  ]
}
```

**Assumptions**:
- DataSource 1 exists and is accessible by current tenant
- LLM Server 1 exists and is accessible by current tenant

**Expected Behavior**:
- System MUST pass all validation stages
- System MUST execute nodes in order `[A, B]`
- System MUST return success status

**Pass Criteria**:
- ✅ No validation errors
- ✅ Execution completes successfully
- ✅ Both nodes executed in correct order

---

## Optional Tests (Recommended)

### Test 7: Context Validity Window

**Objective**: Verify that context is re-validated if execution is delayed.

**Setup**:
- Validate workflow at time T0
- Delete referenced resource at time T1
- Attempt execution at time T2 (T2 - T0 > validity threshold)

**Expected Behavior** (if implemented):
- System SHOULD re-validate context at T2
- System SHOULD detect missing resource and reject

**Pass Criteria**:
- ⚠️ Optional: Context re-validation implemented
- ✅ If implemented: Missing resource detected at T2

---

### Test 8: Validation Caching

**Objective**: Verify that validation results are cached correctly.

**Setup**:
- Validate workflow W at time T0 → Result R0
- Validate identical workflow W at time T1 (no changes) → Result R1

**Expected Behavior** (if caching implemented):
- System MAY return cached result R0 at T1
- If cached: R0 == R1
- Cache MUST be invalidated if workflow changes

**Pass Criteria**:
- ⚠️ Optional: Caching implemented
- ✅ If implemented: Cache hit on identical workflow
- ✅ If implemented: Cache invalidation on workflow change

---

## Test Execution Guide

### Running Tests

1. **Setup test environment** (configure test tenant, resources)
2. **Execute each mandatory test** (Tests 1-6)
3. **Record results** (pass/fail for each test)
4. **Generate compliance report** (see template below)

### Compliance Report Template

```markdown
# NORP-001 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]

## Test Results

| Test | Status | Notes |
|------|--------|-------|
| Test 1: Cycle Rejection | ✅ Pass | |
| Test 2: Deterministic Order | ✅ Pass | |
| Test 3: Missing Resource | ✅ Pass | |
| Test 4: Fail-Fast | ✅ Pass | |
| Test 5: Permission Check | ✅ Pass | |
| Test 6: Valid Execution | ✅ Pass | |

## Compliance Status

✅ **NORP-001 COMPLIANT**

All mandatory tests passed.

## Optional Features

- [ ] Context validity window (Test 7)
- [x] Validation caching (Test 8)
```

---

## Certification

To claim **NORP-001 Certification**:
1. Pass all 6 mandatory tests
2. Publish compliance report
3. Submit to NORP registry (coming soon)

---

**NORP-001 Compliance Tests v1.0**
**© 2026 NeuraScope - Licensed under MIT**
