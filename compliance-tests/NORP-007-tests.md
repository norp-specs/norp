# NORP-007 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-007 (Cost Estimation and Execution Budget Enforcement).

An implementation is **NORP-007 compliant** if and only if it passes **all mandatory tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning orchestration system with cost estimation
- Access to LLM pricing information (or mock pricing)
- Ability to set execution budgets
- Ability to execute workflows and track costs

### Mock Pricing Table (for testing)

```python
MOCK_PRICING = {
    'gpt-4-turbo': {'input': 0.010, 'output': 0.030},
    'claude-3-haiku': {'input': 0.00025, 'output': 0.00125},
    'mistral-large': {'input': 0.004, 'output': 0.012}
}
```

---

## Mandatory Tests

### Test 1: Pre-Execution Cost Estimation

**Objective**: Verify that cost is estimated before execution begins.

**Input**:

```json
{
  "name": "Test 1 - Single LLM Call",
  "nodes": [
    {
      "id": "summarize",
      "type": "llm_call",
      "config": {
        "model": "gpt-4-turbo",
        "prompt": "Summarize this text: [1000 characters]",
        "max_tokens": 1000
      }
    }
  ]
}
```

**Actions**:
1. Call validation/compilation API (pre-execution)
2. Extract cost estimate from response

**Expected Behavior**:
- Cost estimate returned **before execution begins**
- Estimate > $0 (non-zero for billable LLM operation)
- Estimate calculated as:
  - Input: (250 tokens / 1000) × $0.010 = $0.0025
  - Output: (1000 tokens / 1000) × $0.030 = $0.030
  - Total: ~$0.032 (before conservative margin)

**Pass Criteria**:
- ✅ Estimate returned during validation phase
- ✅ Estimate > 0
- ✅ Execution has NOT started

**Rationale**: Proves cost estimation is pre-execution (NORP-001 integration).

---

### Test 2: Budget Enforcement - Rejection

**Objective**: Verify that executions exceeding budget are rejected.

**Setup**:
- Set execution budget = **$1.00**
- Workflow with estimated cost = **$5.00**
- User confirmation = **false** (no override)

**Input**: (Workflow with 5 expensive LLM calls totaling $5)

**Action**: Attempt to execute workflow

**Expected Behavior**:
- Execution **REJECTED** during validation
- Error type: `BUDGET_EXCEEDED` or `COST_ERROR`
- Error message: "Estimated cost $5.00 exceeds budget $1.00"
- **No LLM calls executed** (fail-fast)

**Pass Criteria**:
- ✅ Execution blocked
- ✅ Error type indicates budget violation
- ✅ Error includes estimated cost and budget values
- ✅ Zero billable operations executed (verify via API logs)

**Rationale**: Proves budget enforcement prevents over-budget executions.

---

### Test 3: Budget Enforcement - User Override

**Objective**: Verify that users can explicitly override budget limits when necessary.

**Setup**:
- Execution budget = $1.00
- Workflow with estimated cost = $5.00
- User confirmation = **true** (explicit override)

**Action**: Execute workflow with override flag

**Expected Behavior**:
- Execution **ALLOWED** (user confirmed)
- Warning logged: "Budget exceeded, proceeding with user confirmation"
- Execution completes normally
- Actual cost tracked and reported

**Pass Criteria**:
- ✅ Execution completes successfully
- ✅ Override decision logged
- ✅ Warning emitted in logs
- ✅ Actual cost reported post-execution

**Rationale**: Proves legitimate high-cost workflows can proceed with explicit approval.

---

### Test 4: Actual Cost Tracking

**Objective**: Verify that actual costs are tracked and reported post-execution.

**Input**: Workflow with 2 LLM calls (GPT-4 + Claude Haiku)

**Action**: Execute workflow to completion

**Expected**:
- Workflow executes successfully
- Actual cost computed for each node
- Total actual cost = sum of node costs
- Post-execution report includes:
  - Estimated cost (pre-execution)
  - Actual cost (post-execution)
  - Variance (estimated - actual)

**Pass Criteria**:
- ✅ Actual cost > 0
- ✅ Actual cost tracked per billable node
- ✅ Variance reported
- ✅ Cost data logged for audit

**Example expected output**:

```json
{
  "execution_id": "exec_123",
  "estimated_cost_usd": 0.035,
  "actual_cost_usd": 0.028,
  "variance_usd": 0.007,
  "variance_percent": 20.0,
  "breakdown": [
    {"node_id": "summarize", "model": "gpt-4-turbo", "actual_cost": 0.025},
    {"node_id": "classify", "model": "claude-haiku", "actual_cost": 0.003}
  ]
}
```

**Rationale**: Proves actual cost tracking for auditing and billing.

---

### Test 5: Conservative Estimation Validation

**Objective**: Statistically verify that cost estimation model is conservative (overestimates more often than underestimates).

**Setup**: Execute **100 diverse workflows** with varying:
- Models (GPT-4, Claude, Mistral)
- Token counts (100-5000 tokens)
- Node counts (1-10 nodes)

**Action**:
1. For each workflow: Record estimated_cost and actual_cost
2. Count cases where: estimated_cost >= actual_cost
3. Calculate mean estimation error

**Expected**:
- **≥80 workflows** (out of 100): estimated_cost >= actual_cost
- Mean estimation error: between **-10% and +50%**
  - Negative error = underestimate (bad)
  - Positive error = overestimate (conservative, good)

**Pass Criteria**:
- ✅ At least 80% of executions: estimated >= actual
- ✅ Mean error between -10% and +50%
- ✅ No systematic underestimation (proves conservative model)

**Rationale**: Statistical proof that estimation model is production-safe (leans conservative).

**Note**: This test requires significant sample size (100 executions) and may be run as a batch certification test.

---

## Optional Tests (Recommended)

### Test 6: Runtime Budget Enforcement

**Objective**: Verify that budget is enforced during execution (not just pre-execution).

**Setup**:
- Execution budget = $10.00
- Workflow with 10 LLM calls (estimated $8.00)
- Actual cost of first 5 nodes = $9.50 (higher than estimate)

**Action**: Execute workflow

**Expected** (if runtime enforcement supported):
- Execution **aborts** after 5th node (actual cost $9.50 approaching budget $10)
- Error: `BUDGET_EXCEEDED` (runtime)
- Nodes 6-10 NOT executed

**Expected** (if runtime enforcement NOT supported):
- Execution completes all 10 nodes
- Post-execution cost may exceed budget

**Pass Criteria** (if supported):
- ✅ Execution aborted mid-run
- ✅ Remaining nodes not executed
- ✅ Runtime budget violation logged

**Rationale**: Proves runtime enforcement prevents runaway costs.

---

### Test 7: Multi-Level Budget Interaction

**Objective**: Verify behavior when multiple budget levels are active.

**Setup**:
- Per-execution budget = $10.00
- Per-tenant daily budget = $100.00
- Tenant has already spent $95.00 today

**Input**: Workflow with estimated cost $8.00

**Expected**:
- Per-execution budget: OK ($8 < $10)
- Per-tenant daily budget: **VIOLATED** ($95 + $8 = $103 > $100)
- Execution **REJECTED** (daily budget exceeded)

**Pass Criteria**:
- ✅ Execution blocked
- ✅ Error indicates which budget violated (daily, not per-execution)

**Rationale**: Proves multi-level budget hierarchies work correctly.

---

## Compliance Report Template

```markdown
# NORP-007 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]
**Token Estimation Method**: [Native tokenizer / Approximation (chars/4)]
**Conservative Margin**: [20% / 30% / 50%]

## Test Results

| Test | Status | Estimated | Actual | Budget | Notes |
|------|--------|-----------|--------|--------|-------|
| Test 1: Pre-Execution Estimation | ✅ Pass | $0.035 | - | - | Estimate computed before exec |
| Test 2: Budget Rejection | ✅ Pass | $5.00 | $0.00 | $1.00 | Blocked, no LLM calls |
| Test 3: Budget Override | ✅ Pass | $5.00 | $4.80 | $1.00 | User confirmed, executed |
| Test 4: Actual Cost Tracking | ✅ Pass | $0.035 | $0.028 | - | Variance: 20% |
| Test 5: Conservative Validation | ✅ Pass | - | - | - | 85/100 overestimated |

## Compliance Status

✅ **NORP-007 COMPLIANT**

All mandatory tests passed.

## Statistical Validation (Test 5)

- **Sample size**: 100 workflows
- **Overestimations**: 85 (85%)
- **Underestimations**: 15 (15%)
- **Mean estimation error**: +22% (conservative)

## Optional Features

- [x] Runtime budget enforcement (Test 6)
- [x] Multi-level budgets (Test 7)
- [x] Dynamic pricing updates

## Implementation Details

**Token Counting**: chars / 4 approximation for English
**Pricing Source**: Hardcoded table (updated quarterly)
**Conservative Margin**: 30%
**Budget Levels Supported**: Per-execution, per-tenant daily

## Code Sample

```python
def estimate_cost(workflow):
    total = 0
    for node in workflow.llm_nodes:
        tokens = len(node.prompt) / 4
        cost = (tokens / 1000) * pricing[node.model]['input']
        total += cost
    return total * 1.3  # 30% margin
```
```

---

## Certification

To claim **NORP-007 Certification**:
1. Pass all 5 mandatory tests
2. Document token estimation method
3. Document conservative margin percentage
4. Provide pricing table with last update date
5. Publish compliance report
6. Submit to NORP registry (norp@neurascope.ai)

---

**NORP-007 Compliance Tests v1.2**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
