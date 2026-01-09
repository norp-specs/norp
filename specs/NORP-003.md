# NORP-003
## Immutable Execution State and Deterministic State Transfer

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Stable

### Category
Execution Semantics

### Version
1.2

### Date
2026-01-09

### Authors
NORP Working Group

---

## 1. Abstract

This specification defines mandatory rules for immutable execution state transfer in AI orchestration pipelines.

It ensures that each pipeline stage produces a new, immutable execution state, that no hidden or global state influences execution, and that failures preserve prior valid states.

This specification addresses two orthogonal concerns:
1. **Immutability**: State cannot be mutated after production
2. **Determinism**: Identical input produces identical output

Both are required for NORP-003 compliance.

The goal is to guarantee determinism, auditability, debuggability, and safe execution across complex AI workflows.

---

## 2. Motivation

AI orchestration systems frequently fail due to:
- Hidden state mutations
- Implicit shared context
- Partial execution side effects
- Non-reproducible failures

Mutable execution state leads to non-deterministic behavior, irreproducible bugs, and security blind spots.

**Execution state MUST be treated as a historical record, not a mutable workspace.**

---

## 3. Scope

This specification applies to systems that execute workflows using multiple pipeline stages.

It defines how execution state MUST be produced, transferred, retained, and isolated between stages.

### 3.1 Relationship to NORP-001

This specification is **complementary to NORP-001** (Pre-Execution Validation Pipeline).

- **NORP-001** defines **which stages execute** and in **what order**
- **NORP-003** defines **how state is transferred** between those stages

A fully compliant system SHOULD implement both:
- NORP-001 for pipeline structure
- NORP-003 for immutable state transfer

**Example pipeline** (NORP-001 + NORP-003):

```
[NORP-001 Stage 1] VALIDATION
  → [NORP-003] produces ValidationResult (immutable DTO)

[NORP-001 Stage 2] COMPILATION
  → [NORP-003] consumes ValidationResult
  → produces ExecutionPlan (immutable DTO)

[NORP-001 Stage 3] CONTEXT RESOLUTION
  → [NORP-003] consumes ExecutionPlan
  → produces ExecutionContext (immutable DTO)

[NORP-001 Stage 4] EXECUTION
  → [NORP-003] consumes ExecutionContext
  → produces ExecutionResult (immutable DTO)
```

---

## 4. Terminology

**Execution State**: A data structure representing the complete output of a pipeline stage.

**Stage**: A deterministic processing step that consumes an execution state and produces a new one.

**Mutation**: Any operation that modifies an existing execution state or any of its nested properties.

**DTO** (Data Transfer Object): An immutable structure used to transfer execution state between stages.

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Normative Requirements

### 5.1 Immutable State Production

Each pipeline stage MUST produce a **new execution state**.

Previously produced execution states MUST NOT be mutated.

Execution state MUST be treated as **immutable** once produced.

#### 5.1.1 Immutability Definition

A **"new execution state"** means:
- A **distinct object in memory** (not a reference to existing state)
- **No shared mutable references** with previous states
- **All nested properties are immutable** (deep immutability)

**Shallow copying is NOT sufficient** for NORP-003 compliance.

##### Examples

**Mutation** (PROHIBITED):

```javascript
// ❌ BAD: Modifying existing state
state.validated = true;
state.context.resources.push(newResource);
```

**Immutability** (REQUIRED):

```javascript
// ✅ GOOD: Producing new state
const newState = {
  ...previousState,
  validated: true,
  context: {
    ...previousState.context,
    resources: [...previousState.context.resources, newResource]
  }
};
```

##### Language-specific mechanisms

- **JavaScript/TypeScript**: `Object.freeze()`, `readonly` types, spread operator (`...`)
- **PHP 8.2+**: `readonly` properties
- **Python**: `dataclasses(frozen=True)`, `NamedTuple`
- **Rust**: Default immutability
- **Java**: `final` fields, immutable collections

Systems MUST document which immutability mechanism they use.

---

### 5.2 Explicit State Boundaries

Execution state passed between stages MUST be **explicit**.

Implicit state sharing through:
- Global variables
- Singletons
- Shared memory
- Hidden context containers

is **NOT permitted**.

Each stage MUST declare:
- Its required **input state**
- Its produced **output state**

---

### 5.3 Stage Isolation

Pipeline stages MUST NOT:
- Modify state produced by previous stages
- Depend on undocumented side effects
- Access future stage outputs

Stages MUST operate **only** on their declared input state.

---

### 5.4 Deterministic State Production

Given **identical input state**, a pipeline stage MUST produce **identical output state**.

#### 5.4.1 Determinism Scope

**Pipeline stages** (validation, compilation, context resolution) MUST be **deterministic**.

**Execution nodes** (LLM calls, external APIs) MAY be **non-deterministic**, provided that:
- Non-determinism is **contained within node execution**
- Pipeline **control flow** remains deterministic
- Execution **state structure** remains deterministic (even if values vary)

**Example**:

```json
{
  "node_id": "llm_1",
  "output": {
    "text": "...",  // ← Value may vary (non-deterministic LLM)
    "tokens": 123   // ← Structure is always same (deterministic)
  }
}
```

**Structure MUST be stable** even if LLM output text varies between runs.

---

### 5.5 Failure Semantics

If a pipeline stage fails:
- **No subsequent stage** MAY execute
- **No partial mutation** of prior state is permitted
- The **last valid immutable state** MUST remain intact

#### 5.5.1 State Retention on Failure

The **calling context** (e.g., orchestrator) MUST retain the last valid execution state produced by the previous stage.

The failing stage MUST NOT produce partial state.

State from successful stages MUST be **available for error reporting**.

**Example**:

```php
try {
    $validationResult = $validator->validate($workflow); // ✅ OK
    $executionPlan = $compiler->compile($validationResult); // ❌ Fails
} catch (CompilerException $e) {
    // $validationResult still accessible here
    log("Compilation failed after successful validation", [
        'validation' => $validationResult->toArray(),
        'error' => $e->getMessage()
    ]);
}
```

State rollback mechanisms MAY be implemented but MUST NOT rely on mutable state.

---

### 5.6 Serialization and Snapshotting

Execution state MUST be **serializable** OR provide an **equivalent snapshot mechanism**.

Serialization enables:
- **Debugging** (state inspection)
- **Auditing** (compliance trails)
- **Replay** (deterministic testing)
- **Checkpointing** (failure recovery)

#### 5.6.1 Non-Serializable Objects

State containing non-serializable resources (e.g., database connections, file handles) MUST provide a snapshot that:
- Captures **resource metadata** (connection string, credentials reference)
- **Omits the live object** (connection handle, file descriptor)
- Allows **state reconstruction** from metadata

**Example**:

```json
// ❌ NOT allowed: Serializing PDO connection object
{
  "datasource": {
    "id": 5,
    "connection_handle": "[PDO Object]"
  }
}

// ✅ Allowed: Serialize metadata only
{
  "datasource": {
    "id": 5,
    "type": "mysql",
    "host": "db.example.com",
    "database": "prod_db"
  }
}
```

---

## 6. Fail-Safe Behavior

State validation errors MUST be detected at the earliest possible stage.

Execution MUST NOT proceed if required state is missing, malformed, or inconsistent.

If immutability, isolation, or determinism cannot be enforced, execution MUST be prevented.

---

## 7. Security Considerations

Mutable execution state introduces attack vectors including:
- **State poisoning** (modifying state to escalate privileges)
- **Replay manipulation** (altering historical state)
- **Non-auditable execution paths** (side effects invisible in state)

Execution state MUST be treated as **untrusted** until validated.

Immutability limits the blast radius of failures and enforces clear ownership of execution data.

---

## 8. Implementation Guidance (Non-Normative)

### 8.1 Common Anti-Patterns

#### Mutable DTOs

❌ **BAD**:
```typescript
class ValidationResult {
  valid: boolean;
  errors: string[] = [];

  addError(msg: string) { // ← Mutation method
    this.errors.push(msg);
  }
}
```

✅ **GOOD**:
```typescript
class ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;

  constructor(valid: boolean, errors: string[]) {
    this.valid = valid;
    this.errors = Object.freeze(errors);
  }
}
```

---

#### Hidden Shared State

❌ **BAD**:
```python
class Pipeline:
    _shared_state = {}  # Class variable = shared mutable state

    def execute(self, input):
        self._shared_state['result'] = compute(input)
```

✅ **GOOD**:
```python
from dataclasses import dataclass

@dataclass(frozen=True)
class ExecutionState:
    result: Any

class Pipeline:
    def execute(self, input: ExecutionState) -> ExecutionState:
        return ExecutionState(result=compute(input))
```

---

#### State Passed by Reference

❌ **BAD**:
```php
function validate(array &$state): void {
    $state['validated'] = true; // Mutation via reference
}
```

✅ **GOOD**:
```php
function validate(array $state): ValidationResult {
    return new ValidationResult(
        validated: true,
        original: $state
    );
}
```

---

#### Shallow Copy

❌ **BAD**:
```javascript
// Shallow copy shares nested objects
const newState = {...oldState};
newState.context.resources.push(item); // ← Mutates oldState.context!
```

✅ **GOOD**:
```javascript
// Deep immutability
const newState = {
  ...oldState,
  context: {
    ...oldState.context,
    resources: [...oldState.context.resources, item]
  }
};
```

---

#### TypeScript Mutable Interface

❌ **BAD**:
```typescript
interface ValidationResult {
  valid: boolean;
  errors: string[];
}
// Mutable by default
```

✅ **GOOD**:
```typescript
interface ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;
}
```

---

#### Rust Unnecessary Mutability

❌ **BAD**:
```rust
struct ExecutionState {
    validated: bool,
    errors: Vec<String>
}
// Explicitly mutable
let mut state = ExecutionState { ... };
```

✅ **GOOD**:
```rust
#[derive(Clone)]
struct ExecutionState {
    validated: bool,
    errors: Vec<String>
}
// Immutable by default, clone when needed
let new_state = ExecutionState { ... };
```

---

### 8.2 Code Review Checklist

When reviewing code for NORP-003 compliance:

- [ ] DTOs use `readonly` / `frozen` / `final` properties
- [ ] No setter methods on execution state objects
- [ ] State passed by value or immutable reference
- [ ] No global variables storing execution state
- [ ] No singletons holding mutable execution context
- [ ] Serialization methods exist for all state objects
- [ ] Stage functions are pure (no side effects on input state)
- [ ] Failures preserve prior state (tested)
- [ ] Deep immutability enforced (nested objects also immutable)

---

## 9. Compliance

A system is **NORP-003 compliant** if:
- All execution state is immutable (deep immutability)
- All state is passed explicitly between stages
- No hidden global state influences execution
- All mandatory compliance tests pass

### 9.1 Compliance Test Suite

**Test 1: State Immutability**
- **Setup**: Execute stage S → produces ExecutionState state1
- **Action**: Attempt to mutate state1 property
- **Expected**: Mutation fails (runtime error) OR mutation has no effect on state1
- **Rationale**: Proves state is truly immutable

**Test 2: No Global State Dependency**
- **Setup**: Execute workflow with input I1 → produces output O1
- **Action**: Modify global variable or singleton state
- **Action**: Execute same workflow with same input I1 → produces output O2
- **Expected**: O1 == O2 (proves no hidden global dependency)
- **Rationale**: Ensures execution is self-contained

**Test 3: Stage Isolation**
- **Setup**: Stage A produces ExecutionState stateA
- **Action**: Stage B consumes stateA
- **Action**: Stage B attempts to modify stateA
- **Expected**: stateA remains unchanged (modification fails or ignored)
- **Rationale**: Proves stages cannot mutate prior states

**Test 4: Failure Preserves Prior State**
- **Setup**: Stage A produces valid ExecutionState stateA
- **Action**: Stage B consumes stateA and fails
- **Expected**: stateA remains intact and accessible for error reporting
- **Rationale**: Failed stages don't corrupt successful states

Full test specifications available in `compliance-tests/NORP-003-tests.md`.

---

### 9.2 Optional Tests (Recommended)

**Test 5: Deep Immutability**
- **Setup**: Create ExecutionState with nested objects
- **Action**: Attempt to modify deeply nested property
- **Expected**: Modification fails or has no effect on original
- **Rationale**: Shallow copy detection

**Test 6: Serialization Round-Trip**
- **Setup**: Stage produces ExecutionState S1
- **Action**: Serialize S1 → JSON, then deserialize → S2
- **Expected**: S1 == S2 (structure and values preserved)
- **Rationale**: Ensures auditability and replay capability

---

## 10. Security Considerations

Mutable or hidden state introduces attack vectors such as:
- **State poisoning** (modifying state to escalate privileges)
- **Replay manipulation** (altering historical state for bypass)
- **Non-auditable execution paths** (side effects invisible in state snapshots)

Execution state MUST be treated as **untrusted** until validated.

Immutability limits the blast radius of failures and enforces clear ownership of execution data.

---

## 11. Rationale Summary

**Core Principle**: Execution state is a historical record, not a mutable workspace.

Immutability is the only reliable foundation for determinism, auditability, and safe orchestration.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 12. Future Extensions

Future NORP specifications MAY address:
- Distributed state propagation
- Partial execution replay with checkpointing
- Deterministic rollback strategies
- State versioning and lineage tracking
- Event sourcing integration

---

## 13. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- Functional programming principles (pure functions, immutability)
- Deterministic systems design
- Redux Architecture (state immutability patterns)
- Event Sourcing patterns

---

## 14. Acknowledgments

This specification is derived from production execution patterns at NeuraScope, including the Blueprint Runtime Engine and DTO-based state transfer architecture.

The authors thank reviewers for feedback on immutability enforcement mechanisms.

---

## Appendix A: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | 2026-01-09 | Status upgraded to Stable. Added immutability definition (5.1.1), determinism scope (5.4.1), state retention on failure (5.5.1), serialization guidance (5.6.1), anti-patterns (8.1), code review checklist (8.2), compliance tests (9.1), NORP-001 linkage (3.1). |
| 1.0 | 2026-01-07 | Initial draft. |

---

## Appendix B: Reference DTOs (Non-Normative)

### ValidationResult (PHP)

```php
readonly class ValidationResult {
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly float $estimated_cost,
    ) {}

    public function toArray(): array {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'estimated_cost' => $this->estimated_cost,
        ];
    }
}
```

---

### ExecutionPlan (TypeScript)

```typescript
interface ExecutionPlan {
  readonly nodes: ReadonlyArray<Node>;
  readonly execution_order: ReadonlyArray<string>;
  readonly parallel_groups: ReadonlyArray<ParallelGroup>;
  readonly estimated_duration_ms: number;
}

// Usage
const plan: ExecutionPlan = {
  nodes: Object.freeze([...]),
  execution_order: Object.freeze(['A', 'B', 'C']),
  parallel_groups: Object.freeze([...]),
  estimated_duration_ms: 1500
};
```

---

### ExecutionContext (Python)

```python
from dataclasses import dataclass
from typing import Dict, Any

@dataclass(frozen=True)
class ExecutionContext:
    tenant_id: str
    blueprint_id: str
    execution_id: str
    inputs: Dict[str, Any]
    variables: Dict[str, Any]
    started_at: str

    def to_dict(self) -> dict:
        return {
            'tenant_id': self.tenant_id,
            'blueprint_id': self.blueprint_id,
            'execution_id': self.execution_id,
            'inputs': self.inputs,
            'variables': self.variables,
            'started_at': self.started_at
        }
```

---

## Citation

```bibtex
@techreport{norp003-2026,
  title={{NORP-003: Immutable Execution State and Deterministic State Transfer}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/NORP-003},
  license={CC BY 4.0}
}
```

---

**NORP-003 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
