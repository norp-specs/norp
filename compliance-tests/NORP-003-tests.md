# NORP-003 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-003 (Immutable Execution State and Deterministic State Transfer).

An implementation is **NORP-003 compliant** if and only if it passes **all mandatory tests** in this suite.

---

## Test Environment Setup

### Prerequisites
- A functioning orchestration system with multi-stage pipeline
- Ability to execute workflows programmatically
- Access to execution state between stages
- Logging or introspection capabilities

---

## Mandatory Tests

### Test 1: State Immutability

**Objective**: Verify that execution state cannot be mutated after production.

**Setup**:
- Execute Validation stage → produces ValidationResult state1

**Actions**:
1. Attempt to modify `state1.valid = false` (or equivalent property)
2. Pass state1 to next stage (Compilation)

**Expected Behavior**:

**Option A** (Language enforces immutability):
- Mutation attempt throws runtime error (e.g., TypeError in frozen Python dataclass)

**Option B** (Language allows mutation syntactically):
- Mutation is ignored (state1 remains unchanged)
- Next stage receives original state1 values

**Pass Criteria**:
- ✅ Either mutation throws error OR state1 unchanged after mutation attempt
- ✅ Next stage receives unmodified state1

**Implementation Examples**:

```typescript
// TypeScript with readonly
const state: ValidationResult = { readonly valid: true, ... };
state.valid = false; // ✅ Compile-time error

// JavaScript with Object.freeze
const state = Object.freeze({ valid: true });
state.valid = false; // ✅ Silent fail in strict mode, runtime error in strict mode
console.log(state.valid); // → true (unchanged)
```

```php
// PHP 8.2 with readonly
readonly class ValidationResult {
    public function __construct(public readonly bool $valid) {}
}
$state = new ValidationResult(true);
$state->valid = false; // ✅ Fatal error: Cannot modify readonly property
```

---

### Test 2: No Global State Dependency

**Objective**: Verify that execution state is not influenced by hidden global variables or singletons.

**Setup**:
- Define a simple workflow W
- Define input I1

**Actions**:
1. Execute workflow W with input I1 → produces output O1
2. Modify a global variable or singleton state
3. Execute same workflow W with same input I1 → produces output O2

**Expected Behavior**:
- O1 MUST equal O2 (structurally and semantically)
- Global state modification MUST NOT affect execution

**Pass Criteria**:
- ✅ O1 == O2 (deep equality)
- ✅ Execution is reproducible despite global state changes

**Example**:

```python
# Global variable (should NOT affect execution)
global_counter = 0

# First execution
result1 = orchestrator.execute(workflow, input={'x': 5})

# Modify global
global_counter = 999

# Second execution
result2 = orchestrator.execute(workflow, input={'x': 5})

# Assertion
assert result1 == result2  # ✅ Must pass
```

**If test fails**: System has hidden dependency on global state (NORP-003 violation).

---

### Test 3: Stage Isolation

**Objective**: Verify that a pipeline stage cannot modify state produced by previous stages.

**Setup**:
- Stage A (Validation) produces ExecutionState stateA
- Stage B (Compilation) consumes stateA

**Actions**:
1. Stage B attempts to modify stateA (e.g., `stateA.errors.append("new error")`)
2. Stage B completes (success or failure)
3. Inspect stateA after Stage B completes

**Expected Behavior**:
- stateA MUST remain unchanged
- Either mutation attempt failed OR mutation was isolated to a copy

**Pass Criteria**:
- ✅ stateA is identical before and after Stage B execution
- ✅ If Stage B needed to add data, it produced a NEW state (stateB), not modified stateA

**Example**:

```javascript
// Stage A produces state
const stateA = validator.validate(workflow);
console.log(stateA.errors); // → []

// Stage B attempts modification
function compileStage(inputState) {
  inputState.errors.push("compiler error"); // ← Attempt mutation
  return { compiled: true };
}

const stateB = compileStage(stateA);

// Verification
console.log(stateA.errors); // → MUST still be [] (unchanged)
```

---

### Test 4: Failure Preserves Prior State

**Objective**: Verify that when a stage fails, the last valid state from previous stages remains intact.

**Setup**:
- Stage A (Validation) executes successfully → produces valid stateA
- Stage B (Compilation) fails (e.g., cycle detected, or throws exception)

**Actions**:
1. Execute Stage A → stateA produced
2. Execute Stage B with stateA as input → fails
3. Inspect stateA after Stage B failure

**Expected Behavior**:
- stateA MUST still be accessible
- stateA MUST be unchanged (no partial corruption)
- Error logging SHOULD include stateA data

**Pass Criteria**:
- ✅ stateA accessible in exception handler
- ✅ stateA values unchanged after Stage B failure
- ✅ Error logs can report stateA content

**Example**:

```php
try {
    $stateA = $validator->validate($workflow);
    echo $stateA->valid; // → true

    $stateB = $compiler->compile($stateA); // ← Throws exception

} catch (CompilerException $e) {
    // stateA MUST still be accessible here
    echo $stateA->valid; // → MUST still be true

    Log::error("Compilation failed", [
        'validation_state' => $stateA->toArray(), // ✅ Available
        'error' => $e->getMessage()
    ]);
}
```

---

## Optional Tests (Recommended)

### Test 5: Deep Immutability

**Objective**: Verify that nested objects within execution state are also immutable.

**Setup**:
- Create ExecutionState with nested structure (e.g., `state.context.resources`)

**Actions**:
1. Produce state with nested object
2. Attempt to modify nested property (e.g., `state.context.resources.push(item)`)

**Expected Behavior**:
- Modification fails OR has no effect on original state

**Pass Criteria**:
- ✅ Nested property unchanged after mutation attempt
- ✅ Deep equality check passes (state === original)

**Example**:

```javascript
const state = {
  context: Object.freeze({
    resources: Object.freeze([1, 2, 3])
  })
};

state.context.resources.push(4); // ✅ Throws error or silent fail
console.log(state.context.resources); // → [1, 2, 3] (unchanged)
```

---

### Test 6: Serialization Round-Trip

**Objective**: Verify that execution state can be serialized and deserialized without data loss.

**Setup**:
- Execute stage → produces ExecutionState S1

**Actions**:
1. Serialize S1 to JSON string
2. Deserialize JSON back to object S2

**Expected Behavior**:
- S1 and S2 MUST be structurally equivalent
- All non-serializable objects MUST be replaced by metadata

**Pass Criteria**:
- ✅ S1 == S2 (deep equality on serializable fields)
- ✅ Non-serializable objects (connections, handles) replaced by metadata

**Example**:

```python
# Produce state
state1 = ExecutionContext(
    tenant_id="acme",
    blueprint_id="bp_123",
    execution_id="exec_456",
    inputs={"x": 5}
)

# Serialize
json_str = json.dumps(state1.to_dict())

# Deserialize
state2 = ExecutionContext(**json.loads(json_str))

# Assertion
assert state1 == state2  # ✅ Must pass
```

---

## Compliance Report Template

```markdown
# NORP-003 Compliance Report

**System**: [Your System Name]
**Version**: [Version Number]
**Date**: [Test Date]
**Language**: [PHP / TypeScript / Python / Other]
**Immutability Mechanism**: [readonly / frozen / Object.freeze / final]

## Test Results

| Test | Status | Notes |
|------|--------|-------|
| Test 1: State Immutability | ✅ Pass | Language enforces via readonly |
| Test 2: No Global Dependency | ✅ Pass | |
| Test 3: Stage Isolation | ✅ Pass | |
| Test 4: Failure Preservation | ✅ Pass | |
| Test 5: Deep Immutability (optional) | ✅ Pass | |
| Test 6: Serialization (optional) | ✅ Pass | |

## Compliance Status

✅ **NORP-003 COMPLIANT**

All mandatory tests passed.

## Implementation Details

- **DTOs used**: ValidationResult, ExecutionPlan, ExecutionContext, ExecutionResult
- **Immutability enforced via**: PHP 8.2 `readonly` properties
- **Serialization**: JSON via `toArray()` methods
- **Non-serializable objects**: PDO connections replaced with metadata

## Code Sample

```php
readonly class ValidationResult {
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
    ) {}
}
```
```

---

## Certification

To claim **NORP-003 Certification**:
1. Pass all 4 mandatory tests
2. Document immutability mechanism used
3. Publish compliance report
4. Submit to NORP registry (norp@neurascope.ai)

---

**NORP-003 Compliance Tests v1.2**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
