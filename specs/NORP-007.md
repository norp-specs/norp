# NORP-007
## Cost Estimation and Execution Budget Enforcement

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Stable

### Category
Cost Governance

### Version
1.2

### Date
2026-01-09

### Authors
NORP Working Group

---

## 1. Abstract

This specification defines mandatory requirements for **cost estimation, budget enforcement, and cost observability** in AI orchestration systems.

It ensures that execution cost is:
- Estimated deterministically before execution
- Enforced against explicit budgets
- Measured and reported after execution

The objective is to prevent uncontrolled spending, enable governance, and ensure predictability of AI system costs.

---

## 2. Motivation

AI workflows often involve variable and potentially expensive operations:
- LLM inference (variable token costs)
- External API calls (metered billing)
- Vector database queries (compute costs)
- Tool invocations billed per request

Without explicit cost controls, systems may suffer from:
- **Budget overruns** (unexpected $10,000+ bills)
- **Unpredictable billing** (workflows with variable costs)
- **Abuse or denial-of-wallet attacks** (malicious high-cost workflows)
- **Lack of auditability** (cannot attribute costs to executions)

Cost estimation and enforcement are therefore **first-class execution invariants**.

---

## 3. Scope

This specification applies to systems that:
- Execute workflows with billable operations
- Can estimate cost prior to execution
- Enforce execution budgets

### 3.1 Relationship to NORP-001

- **NORP-001** defines execution validation and pre-execution checks
- **NORP-007** extends NORP-001 by adding **cost validation** as a mandatory phase

Cost estimation MUST occur:
- After structural validation (NORP-004)
- After ordering resolution (NORP-005)
- **Before execution start** (as part of Context Resolution stage)

**Integration with NORP-001 pipeline**:
```
NORP-001 Stage 1: Structural Validation
NORP-001 Stage 2: Compilation
NORP-001 Stage 3: Context Resolution
  → [NORP-007] Cost Estimation ✅
  → [NORP-007] Budget Enforcement ✅
NORP-001 Stage 4: Execution (only if budget OK)
```

---

## 4. Terminology

**Estimated Cost**: Projected monetary cost computed before execution begins.

**Actual Cost**: Measured monetary cost incurred during execution.

**Budget**: Maximum allowed cost for a defined scope (execution, workflow, tenant, period).

**Billable Operation**: Any operation with a monetary cost (LLM API call, paid API request, metered compute).

**Billing Unit**: The unit used for pricing (e.g., per 1,000 tokens, per request, per compute hour).

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Normative Requirements

### 5.1 Cost Estimation

Systems MUST compute a cost estimate before execution.

Cost estimation MUST:
- Include **all billable operations** in the workflow
- Be **deterministic** for identical workflows
- Return a **numeric value > 0** when billable operations exist
- Be computed **before execution begins** (fail-fast if budget exceeded)

---

#### 5.1.1 Token Counting

For LLM calls, systems MUST estimate token usage prior to execution.

If a **native tokenizer** for the target model is available (e.g., `tiktoken`, `sentencepiece`), systems SHOULD use exact token counts.

If no native tokenizer is available, systems MUST use a **conservative approximation**.

**Recommended approximations**:

- For predominantly **English text**:
  ```
  estimated_tokens = characters / 4
  ```

- For **multilingual or mixed text**:
  ```
  estimated_tokens = characters × 0.3
  ```

These approximations are based on empirical averages observed across modern LLM tokenizers (GPT, Claude, Llama).

**Example**:
```
Prompt: "Explain quantum computing"
Character count: 25
Estimated tokens: 25 / 4 ≈ 6 tokens
```

Systems MUST document which estimation method is used (native tokenizer vs approximation).

---

#### 5.1.2 Pricing Model

Systems MUST associate each billable operation with a **pricing model**.

Pricing MUST specify:
- **Input unit price** (e.g., $0.010 per 1K input tokens)
- **Output unit price** (e.g., $0.030 per 1K output tokens)
- **Billing unit** (e.g., per 1,000 tokens, per API request)

Pricing SHOULD be:
- Fetched from provider APIs when available (dynamic pricing)
- Updated quarterly minimum (if hardcoded)
- Documented with last update date

---

#### 5.1.3 Cost Formula

For LLM calls, estimated cost MUST be computed as:

```
estimated_cost =
  (input_tokens / billing_unit × input_price) +
  (output_tokens / billing_unit × output_price)
```

**Example**:
```
Model: GPT-4 Turbo
Pricing: $0.010 per 1K input, $0.030 per 1K output
Billing unit: 1,000 tokens

Prompt: 1,000 tokens (input)
Max output: 500 tokens

Cost calculation:
  Input cost:  (1000 / 1000) × $0.010 = $0.010
  Output cost: (500 / 1000) × $0.030  = $0.015
  Total:       $0.010 + $0.015        = $0.025
```

**Total workflow cost** is the sum of all node estimates.

---

#### 5.1.4 Conservative Estimation

Cost estimation SHOULD be **conservative** (prefer overestimation to underestimation).

**Conservative estimation means**:
- Include a safety margin to account for:
  - Token count variation (+10-20%)
  - API pricing changes
  - Retry attempts (transient failures)

**Recommended margin**: **20% to 50%** above minimum expected cost.

**Example**:
```
Minimum expected cost: $1.00
Conservative estimate: $1.20 (20% margin) to $1.50 (50% margin)
```

Systems SHOULD document their safety margin percentage.

---

### 5.2 Budget Definition

Budgets MAY be defined at **multiple levels**:

- **Per-execution budget**: Maximum cost for a single workflow run
- **Per-workflow budget**: Maximum cost per workflow definition (cumulative)
- **Per-tenant budget**: Maximum cost per tenant per period (daily, monthly)

Systems MUST document which budget levels are supported.

---

#### 5.2.1 Budget Scope Examples

**Example 1: Per-Execution Budget**
```json
{
  "workflow_id": "wf_123",
  "budget": {
    "type": "per_execution",
    "limit_usd": 10.00
  }
}
```

If estimated cost > $10 → Reject execution

---

**Example 2: Per-Tenant Daily Budget**
```json
{
  "tenant_id": "acme",
  "budget": {
    "type": "daily",
    "limit_usd": 1000.00,
    "spent_today_usd": 850.00
  }
}
```

If (spent_today + estimated_cost) > $1000 → Reject execution

---

**Example 3: Per-Workflow Cumulative Budget**
```json
{
  "workflow_id": "wf_123",
  "budget": {
    "type": "cumulative",
    "limit_usd": 5000.00,
    "total_spent_usd": 4700.00
  }
}
```

If (total_spent + estimated_cost) > $5000 → Reject or warn

---

#### 5.2.2 Budget Enforcement Timing

Budget enforcement MUST occur:

**1. Pre-execution** (MANDATORY):
- Estimated cost MUST be compared against the applicable budget
- Execution MUST be blocked if the budget is exceeded, unless an **explicit override** is provided (user confirmation)

Budget enforcement SHOULD occur:

**2. During execution** (RECOMMENDED):
- Systems SHOULD track actual cost incrementally
- Systems SHOULD enforce the budget at runtime

Systems supporting **runtime enforcement** MUST:
- Track actual cost per billable operation
- **Abort execution** when the budget is exceeded mid-run
- Emit a **budget violation event** for observability and audit

Systems that do **NOT** support runtime enforcement MUST clearly **document this limitation**.

**Pre-execution enforcement** (pseudocode):

```python
if estimated_cost > budget:
    if not user_confirmed:
        raise BudgetExceededError(
            f"Estimated cost ${estimated_cost:.2f} exceeds budget ${budget:.2f}"
        )
```

**Runtime enforcement** (optional, pseudocode):

```python
accumulated_cost = 0

for node in workflow.nodes:
    result = execute_node(node)
    accumulated_cost += result.cost

    if accumulated_cost > budget:
        abort_execution()
        raise BudgetExceededError(
            f"Actual cost ${accumulated_cost:.2f} exceeded budget ${budget:.2f} during execution"
        )
```

---

### 5.3 Cost Transparency and Observability

Systems MUST expose:
- **Estimated cost** before execution (returned during validation)
- **Actual cost** after execution (logged and returned)
- **Budget threshold** used (which budget level applied)
- **Enforcement decision** (allowed, blocked, overridden with user confirmation)

Cost diagnostics MUST be **machine-readable** (structured format).

**Example diagnostic**:

```json
{
  "execution_id": "exec_abc123",
  "estimated_cost_usd": 2.50,
  "budget_usd": 10.00,
  "enforcement_decision": "ALLOWED",
  "breakdown": [
    {"node_id": "summarize", "model": "gpt-4", "estimated_cost": 2.00},
    {"node_id": "classify", "model": "claude-haiku", "estimated_cost": 0.50}
  ]
}
```

---

### 5.4 Actual Cost Tracking

Systems SHOULD track **actual cost** during execution.

Actual cost tracking enables:
- **Validation of estimates** (estimated vs actual comparison)
- **Refinement of estimation models** (improve accuracy over time)
- **Detection of anomalies** (actual >> estimated = potential issue)

Significant deviation from estimates (>50%) SHOULD be logged as a warning.

---

## 6. Security Considerations

Lack of cost enforcement may enable:
- **Denial-of-wallet attacks** (malicious workflows designed to maximize costs)
- **Abuse of paid APIs** (unauthorized usage driving up bills)
- **Unbounded resource consumption** (runaway workflows)

Cost governance is therefore a **security requirement**, not just financial prudence.

Implementers SHOULD assume:
- Workflows may be crafted to maximize costs
- Budget bypass attempts will occur
- Cost estimation may be gamed (undeclared billable operations)

---

## 7. Rationale Summary

**Core Principle**: Cost predictability is mandatory for trustworthy AI orchestration systems.

Execution without cost estimation and budget enforcement exposes organizations to unbounded financial risk.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 8. Implementation Guidance (Non-Normative)

### 8.1 Common Anti-Patterns

#### Anti-Pattern 1: No Cost Estimation

❌ **BAD**: Execute without estimating cost
```python
# Directly execute without cost check
execute_workflow(workflow)
```

✅ **GOOD**: Estimate and validate budget first
```python
estimate = estimate_cost(workflow)
validate_budget(estimate, budget)
execute_workflow(workflow)
```

**Why**: Prevents surprise bills and budget overruns.

---

#### Anti-Pattern 2: Underestimation (No Safety Margin)

❌ **BAD**: Use exact minimum cost
```python
estimate = exact_minimum_cost(workflow)
```

✅ **GOOD**: Add conservative margin
```python
exact_cost = calculate_cost(workflow)
estimate = exact_cost * 1.3  # 30% safety margin
```

**Why**: LLM token counts vary, API pricing changes, retries add cost.

---

#### Anti-Pattern 3: Ignoring Budget at Runtime

❌ **BAD**: Continue execution despite exceeding budget
```python
if actual_cost > budget:
    log.warning("Budget exceeded, continuing anyway")
    continue_execution()  # ❌ Ignores budget
```

✅ **GOOD**: Abort execution when budget exceeded
```python
if actual_cost > budget:
    abort_execution()
    raise BudgetExceededError("Actual cost exceeded budget during execution")
```

**Why**: Runtime enforcement prevents runaway costs.

---

### 8.2 Cost Estimation Implementation (Recommended)

```python
def estimate_workflow_cost(workflow, pricing_table):
    """
    Estimate total workflow cost based on LLM nodes.

    Returns: Estimated cost in USD
    """
    total_cost = 0.0

    for node in workflow.nodes:
        if node.type == 'llm_call':
            # Get pricing for model
            pricing = pricing_table.get(node.config['model'])

            # Estimate input tokens
            prompt = node.config['prompt']
            input_tokens = len(prompt) / 4  # English approximation

            # Max output tokens
            output_tokens = node.config.get('max_tokens', 1000)

            # Calculate cost
            input_cost = (input_tokens / 1000) * pricing['input']
            output_cost = (output_tokens / 1000) * pricing['output']

            node_cost = input_cost + output_cost
            total_cost += node_cost

    # Conservative margin (30%)
    conservative_estimate = total_cost * 1.3

    return round(conservative_estimate, 4)
```

---

## 9. Compliance

A system is **NORP-007 compliant** if:
- Cost is **estimated before execution** (pre-execution requirement)
- Budgets are **enforced** (execution blocked if exceeded, unless overridden)
- **Diagnostics are exposed** (estimated cost, budget, decision)
- All mandatory compliance tests **pass**

### 9.1 Compliance Test Suite

**Test 1: Pre-Execution Cost Estimation**

**Input**: Workflow with 1 LLM call (GPT-4, max_tokens=1000)

**Action**: Validate workflow (pre-execution phase)

**Expected**:
- Cost estimate returned **before execution**
- Estimate > $0 (non-zero for billable operation)
- Estimate includes both input and output token costs

**Pass Criteria**:
- ✅ Estimate computed during validation
- ✅ Estimate > 0
- ✅ Execution has NOT started yet

**Rationale**: Proves cost estimation occurs pre-execution (NORP-001 compliance).

---

**Test 2: Budget Enforcement - Rejection**

**Setup**:
- Execution budget = $1.00
- Workflow with estimated cost = $5.00
- User confirmation = false

**Action**: Attempt to execute workflow

**Expected**:
- Execution **REJECTED**
- Error type: `BUDGET_EXCEEDED`
- Error message includes estimated cost and budget limit
- **No LLM calls executed** (fail-fast)

**Pass Criteria**:
- ✅ Execution blocked
- ✅ Error type = `BUDGET_EXCEEDED`
- ✅ No billable operations executed

**Rationale**: Proves budget enforcement prevents over-budget executions.

---

**Test 3: Budget Enforcement - User Override**

**Setup**:
- Execution budget = $1.00
- Workflow with estimated cost = $5.00
- User confirmation = **true** (explicit override)

**Action**: Execute workflow

**Expected**:
- Execution **ALLOWED** (user confirmed override)
- Warning logged about budget exceed
- Execution proceeds normally

**Pass Criteria**:
- ✅ Execution completes
- ✅ Override logged
- ✅ Warning emitted

**Rationale**: Proves user can override budget for legitimate high-cost workflows.

---

**Test 4: Actual Cost Tracking**

**Input**: Workflow with 2 LLM calls (known costs)

**Action**: Execute workflow to completion

**Expected**:
- Actual cost computed **post-execution**
- Actual cost logged
- Actual cost ≈ estimated cost (within reasonable range)

**Pass Criteria**:
- ✅ Actual cost returned after execution
- ✅ Actual cost tracked per node
- ✅ Total actual cost = sum of node costs

**Rationale**: Proves actual cost tracking works (for auditing and model refinement).

---

**Test 5: Conservative Estimation Validation**

**Objective**: Verify that cost estimates are conservative (tend to overestimate, not underestimate).

**Setup**: Execute 100 workflows with varied LLM calls

**Action**:
- For each workflow: Compare estimated_cost vs actual_cost
- Count how many times: estimated_cost >= actual_cost

**Expected**:
- **≥80% of executions**: estimated_cost >= actual_cost
- Proves estimation is conservative (safe overestimation)

**Pass Criteria**:
- ✅ In at least 80 out of 100 executions: estimated >= actual
- ✅ Mean estimation error: -10% to +50% (negative = underestimate, positive = overestimate)

**Rationale**: Statistical validation that estimation model is conservative.

---

## 10. Security Considerations

Lack of cost controls enables:
- **Denial-of-wallet attacks** (attacker submits high-cost workflows to drain budget)
- **Cost amplification** (malicious workflow with loops triggering $10,000+ bill)
- **Quota exhaustion** (consuming entire tenant monthly budget in minutes)

Cost governance is therefore a **security requirement**.

Implementers SHOULD assume:
- Workflows may be crafted to maximize costs
- Budget values may be manipulated
- Estimation may be bypassed if not enforced

---

## 11. Rationale Summary

**Core Principle**: Execution without cost estimation and budget enforcement exposes organizations to unbounded financial risk.

Cost predictability, transparency, and enforcement are **non-negotiable** for production AI systems.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 12. Future Extensions

Future NORP specifications MAY define:
- Multi-currency support and conversion
- Dynamic pricing updates from provider APIs
- Cost optimization suggestions (e.g., "Use Claude Haiku instead of GPT-4 to save $2")
- Cost allocation and chargeback mechanisms
- Quota management and rate limiting

---

## 13. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- OpenAI Pricing Documentation
- Anthropic Claude Pricing
- Cloud cost governance best practices
- FinOps Foundation principles

---

## 14. Acknowledgments

This specification is derived from cost estimation implementation in NeuraScope BlueprintValidator (production-tested on 10,000+ workflows).

The authors thank reviewers for feedback on pricing models and budget enforcement semantics.

---

## Appendix A: Example Cost Estimation Workflow

### Complete Workflow with Cost Breakdown

```json
{
  "name": "Content Processing Workflow",
  "nodes": [
    {
      "id": "summarize",
      "type": "llm_call",
      "config": {
        "model": "gpt-4-turbo",
        "prompt": "Summarize this article: [1000 characters]",
        "max_tokens": 500
      }
    },
    {
      "id": "classify",
      "type": "llm_call",
      "config": {
        "model": "claude-3-haiku",
        "prompt": "Classify sentiment: [500 characters]",
        "max_tokens": 200
      }
    }
  ]
}
```

### Cost Calculation

**Node 1: "summarize" (GPT-4 Turbo)**
- Prompt: 1000 characters → ~250 tokens (chars / 4)
- Max output: 500 tokens
- Pricing: Input $0.010/1K, Output $0.030/1K
- Cost:
  - Input: (250 / 1000) × $0.010 = $0.0025
  - Output: (500 / 1000) × $0.030 = $0.015
  - **Node 1 total**: $0.0175

**Node 2: "classify" (Claude 3 Haiku)**
- Prompt: 500 characters → ~125 tokens
- Max output: 200 tokens
- Pricing: Input $0.00025/1K, Output $0.00125/1K
- Cost:
  - Input: (125 / 1000) × $0.00025 = $0.00003125
  - Output: (200 / 1000) × $0.00125 = $0.00025
  - **Node 2 total**: $0.00028125

**Total estimated cost**: $0.0175 + $0.00028125 = **$0.01778125**

**Conservative estimate (30% margin)**: $0.01778 × 1.3 = **$0.023** (~$0.03)

---

## Appendix B: LLM Pricing Reference (Non-Normative)

**⚠️ Pricing is subject to change.**

This table reflects publicly available pricing as of **2026-01-09** (2026 Q1).

Systems SHOULD:
- Review pricing at least **quarterly**
- Update pricing when providers publish changes
- Fetch pricing **dynamically from provider APIs** when available

**Last updated**: 2026-01-09
**Next recommended review**: 2026-04-01

---

### Pricing Table (2026 Q1)

| Provider | Model | Input ($/1K tokens) | Output ($/1K tokens) |
|----------|-------|---------------------|----------------------|
| **OpenAI** | GPT-4 Turbo | $0.010 | $0.030 |
| **OpenAI** | GPT-3.5 Turbo | $0.0005 | $0.0015 |
| **Anthropic** | Claude 3.5 Sonnet | $0.003 | $0.015 |
| **Anthropic** | Claude 3 Haiku | $0.00025 | $0.00125 |
| **Mistral** | Mistral Large | $0.004 | $0.012 |
| **Mistral** | Mistral Small | $0.001 | $0.003 |
| **Local (MLX)** | Llama 3 70B | $0.000 | $0.000 |
| **Local (MLX)** | Qwen 2.5 | $0.000 | $0.000 |

**Notes**:
- Local models (MLX, Ollama) have zero API cost but incur infrastructure costs (electricity, hardware amortization)
- Pricing accurate as of 2026-01-09, subject to provider changes

---

## Appendix C: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | 2026-01-09 | Status upgraded to Stable. Added token counting algorithm (5.1.1), pricing model (5.1.2), cost formula (5.1.3), conservative estimation (5.1.4), budget levels (5.2), budget scope examples (5.2.1), enforcement timing (5.2.2), cost transparency (5.3), actual cost tracking (5.4), security considerations (Section 6), anti-patterns (8.1), cost estimation implementation (8.2), compliance tests (9.1), example workflow with breakdown (Appendix A), pricing table (Appendix B), NORP-001 linkage (3.1). |
| 1.0 | 2026-01-07 | Initial draft. |

---

## Citation

```bibtex
@techreport{norp007-2026,
  title={{NORP-007: Cost Estimation and Execution Budget Enforcement}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/NORP-007},
  license={CC BY 4.0}
}
```

---

**NORP-007 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
