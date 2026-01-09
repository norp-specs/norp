# NORP-006
## Execution Context Isolation and Resource Lifetime Management

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Stable

### Category
Execution Semantics and Performance

### Version
1.2

### Date
2026-01-09

### Authors
NORP Working Group

---

## 1. Abstract

This specification defines strict requirements for execution context isolation and resource lifetime management in AI orchestration systems.

It ensures that resources such as database connections, API clients, model sessions, and caches are scoped to a single execution context, preventing cross-execution leakage, unintended reuse, and multi-tenant boundary violations.

The objective is to balance performance optimization (resource pooling within execution) with security guarantees (strict isolation between executions).

---

## 2. Motivation

AI orchestration systems frequently manage expensive and stateful resources:
- Database connections (50-200ms initialization time)
- API clients with credentials
- Model inference sessions
- In-memory caches

If resource lifetimes are not strictly scoped, systems may suffer from:
- **Cross-execution data leakage** (Execution 1 data visible to Execution 2)
- **Credential reuse across tenants** (security breach)
- **Non-deterministic behavior** (execution depends on prior execution state)
- **Security boundary violations** (tenant isolation compromised)

This specification enforces **execution-scoped isolation** as a hard invariant.

---

## 3. Scope

This specification applies to systems that:
- Execute workflows with identifiable execution boundaries
- Allocate reusable or stateful resources during execution
- Support parallel or concurrent executions (same or different tenants)

### 3.1 Relationship to Other NORP Specifications

- **NORP-001** defines the execution pipeline and lifecycle stages
- **NORP-002** defines **tenant-level** resource isolation (WHO can access resources)
- **NORP-006** defines **execution-level** isolation within a tenant (WHEN and FOR HOW LONG resources exist)

**Relationship summary**:

```
NORP-002: Tenant isolation (WHO)
  → Tenant A cannot access Tenant B resources

NORP-006: Execution isolation (WHEN/HOW LONG)
  → Execution 1 cannot reuse Execution 2 resources
  → EVEN IF same tenant

Combined (NORP-002 + NORP-006):
  → Tenant A Execution 1 ≠ Tenant A Execution 2
  → Tenant A Execution 1 ≠ Tenant B Execution 1
```

Both MUST be implemented for full isolation guarantees.

---

## 4. Terminology

**Execution**: A single, bounded runtime invocation of a workflow.

**Execution Context**: The isolated environment containing metadata (tenant_id, blueprint_id, inputs) and runtime state (resource handles, variables) for one execution.

**Execution Identifier**: A unique identifier (UUID) assigned at execution start.

**Resource**: Any stateful or reusable object including:
- Database connections (PDO, JDBC, psycopg2)
- API clients (Guzzle, Axios, requests)
- Model sessions (OpenAI client, Anthropic SDK)
- Caches (Redis clients, in-memory maps)

**Pooling**: Reuse of a resource instance across multiple nodes within the **same execution**.

**Resource Handle**: A reference to an initialized external resource (e.g., PDO connection, HTTP client).

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Normative Requirements

### 5.1 Execution Identity

Each workflow execution MUST be assigned a **unique execution identifier**.

The execution identifier MUST:
- Be generated at **execution start** (before any resource allocation)
- Be **immutable** throughout execution
- Be **globally unique** (UUID v4 or equivalent)
- Be used to **scope all runtime resources**

**Example**:
```
execution_id: "exec_a1b2c3d4-e5f6-7890-abcd-ef1234567890"
```

All resources allocated during this execution MUST be tagged with this identifier.

---

### 5.2 Execution-Scoped Resource Lifetime

Resources MUST be scoped to **exactly one execution context**.

A resource MAY be reused:
- Across **multiple nodes** within the **SAME execution**
- Only if explicitly declared as execution-scoped

A resource MUST NOT be reused:
- Across **different executions** (even same tenant, same workflow)
- Across **different tenants** (NORP-002 enforcement)
- **After execution termination** (cleanup completed)

---

### 5.3 Resource Pooling Rules

**Pooling** (reuse within execution) is permitted ONLY within a single execution context.

#### 5.3.1 What Pooling Means

**Pooling = Reusing same resource instance**:
- Same database connection used by 5 nodes in one execution ✅
- Same API client used by 3 LLM calls in one execution ✅

**Pooling ≠ Caching results**:
- Storing LLM output for reuse (different concern, see NORP-003)

**Pooling ≠ Cross-execution reuse**:
- DB connection from Execution 1 reused by Execution 2 ❌

---

#### 5.3.2 Pooling Benefits and Constraints

**Benefits** (why pooling within execution is allowed):
- **Performance**: Connection initialization = 50-200ms. Workflow with 10 nodes = 500-2000ms saved.
- **Resource limits**: Database max connections = 100. Without pooling, 10 parallel executions × 10 nodes = 100 connections (limit reached).

**Constraints** (why cross-execution pooling is prohibited):
- **Isolation**: Execution 1 data must NOT leak to Execution 2
- **Determinism**: Execution 2 must NOT depend on Execution 1 state
- **Security**: Credentials for Execution 1 must be invalidated before Execution 2

---

### 5.4 Resource Creation and Disposal

Resources MUST:
- Be created **lazily** (on first use) OR **eagerly** (during context resolution)
- Be **disposed of deterministically** at execution end

Execution termination (success **OR** failure) MUST trigger:
- **Resource cleanup** (connections closed, clients destroyed)
- **Credential invalidation** (tokens revoked if applicable)
- **Cache eviction** (execution-scoped cache cleared)

#### 5.4.1 Cleanup Guarantees

Resource cleanup MUST occur **even if**:
- Execution fails mid-workflow
- Exception thrown during execution
- Timeout occurs

Systems SHOULD use **try-finally** or equivalent patterns to guarantee cleanup.

**Example**:

```python
def execute_workflow(workflow, context):
    try:
        # Load resources
        context.load_resources()

        # Execute nodes
        for node in workflow.nodes:
            execute_node(node, context)

    finally:
        # ALWAYS cleanup (even if exception)
        context.cleanup()
```

---

### 5.5 Failure Semantics

If execution fails:
- Resources MUST NOT be reused by subsequent executions
- Partially initialized resources MUST be disposed immediately
- No resource state may survive the execution boundary

---

## 6. Security Considerations

Failure to enforce execution-scoped isolation can result in:
- **Credential leakage** (API token from Execution 1 reused by Execution 2)
- **Cross-tenant data exposure** (Connection with Tenant A credentials reused for Tenant B)
- **Replay attacks** (Malicious execution reuses authenticated session from prior execution)
- **State contamination** (Cache from Execution 1 pollutes Execution 2)

**Execution-scoped isolation is a security requirement**, not just a performance optimization.

Isolation MUST take precedence over performance.

---

## 7. Diagnostic and Observability

Systems SHOULD expose:
- **Execution identifier in logs** (every log entry tagged with `execution_id`)
- **Resource allocation events** (when resource created, which execution owns it)
- **Resource disposal events** (when resource destroyed, cleanup success/failure)
- **Resource ownership metadata** (which resources belong to which execution)

**Example log entry**:

```json
{
  "timestamp": "2026-01-09T10:15:30Z",
  "level": "INFO",
  "execution_id": "exec_abc123",
  "tenant_id": "acme",
  "event": "RESOURCE_ALLOCATED",
  "resource_type": "database_connection",
  "resource_id": "conn_456"
}
```

---

## 8. Implementation Guidance (Non-Normative)

### 8.1 Common Anti-Patterns

#### Anti-Pattern 1: Global Connection Pool

❌ **BAD**: Global static connection pool
```php
class GlobalDB {
    public static PDO $connection;

    public static function get(): PDO {
        if (!self::$connection) {
            self::$connection = new PDO(...);
        }
        return self::$connection; // ❌ Shared across executions
    }
}
```

✅ **GOOD**: Execution-scoped connection
```php
class ExecutionContext {
    private ?PDO $connection = null;

    public function __construct(
        public readonly string $execution_id,
        public readonly string $tenant_id
    ) {}

    public function getConnection(): PDO {
        if (!$this->connection) {
            $this->connection = new PDO(...); // ✅ Scoped to this execution
        }
        return $this->connection;
    }

    public function cleanup(): void {
        if ($this->connection) {
            $this->connection = null; // Close connection
        }
    }
}
```

---

#### Anti-Pattern 2: Singleton API Client

❌ **BAD**: Singleton with global credentials
```python
# Global singleton (persists across executions)
api_client = APIClient(token=GLOBAL_TOKEN)

def execute_node(node):
    result = api_client.call(node.endpoint) # ❌ Same client across executions
```

✅ **GOOD**: Execution-scoped client
```python
class ExecutionContext:
    def __init__(self, execution_id: str, credentials: dict):
        self.execution_id = execution_id
        self.client = APIClient(token=credentials['api_token']) # ✅ Scoped

    def get_client(self) -> APIClient:
        return self.client

    def cleanup(self):
        if self.client:
            self.client.close()
```

---

#### Anti-Pattern 3: Cross-Execution Cache

❌ **BAD**: Cache key without execution scope
```javascript
// Cache shared across executions
const cache = new Map();
cache.set('model_session', session); // ❌ No execution scoping
```

✅ **GOOD**: Execution-scoped cache keys
```javascript
class ExecutionContext {
    constructor(executionId, tenantId) {
        this.executionId = executionId;
        this.cache = new Map(); // ✅ Instance per execution
    }

    setCache(key, value) {
        const scopedKey = `exec:${this.executionId}:${key}`;
        globalCache.set(scopedKey, value); // ✅ Scoped key
    }

    cleanup() {
        // Evict all keys for this execution
        globalCache.deletePattern(`exec:${this.executionId}:*`);
    }
}
```

---

### 8.2 Resource Lifecycle Pattern (Recommended)

```python
class ExecutionContext:
    def __init__(self, execution_id: str, tenant_id: str):
        self.execution_id = execution_id
        self.tenant_id = tenant_id
        self._resources = {}

    def get_resource(self, resource_type: str, resource_id: int):
        """Lazy load resource (pooling within execution)"""
        key = f"{resource_type}:{resource_id}"

        if key not in self._resources:
            # Load resource (DB connection, API client, etc.)
            self._resources[key] = self._load_resource(resource_type, resource_id)

        return self._resources[key]

    def _load_resource(self, resource_type: str, resource_id: int):
        # Load based on type (DB, API, etc.)
        # Validate tenant ownership (NORP-002)
        pass

    def cleanup(self):
        """Dispose all resources at execution end"""
        for resource in self._resources.values():
            if hasattr(resource, 'close'):
                resource.close()
        self._resources.clear()
```

**Usage**:
```python
try:
    context = ExecutionContext(execution_id="exec_123", tenant_id="acme")

    # Node 1 uses DB connection
    db = context.get_resource('database', 5)  # Creates connection

    # Node 2 uses same DB connection (pooling)
    db = context.get_resource('database', 5)  # Reuses connection ✅

finally:
    context.cleanup()  # Always cleanup
```

---

## 9. Compliance

A system is **NORP-006 compliant** if:
- All resources are **execution-scoped** (unique execution_id assigned)
- No resource survives **execution termination** (cleanup guaranteed)
- **Pooling** is limited to single execution (no cross-execution reuse)
- All mandatory compliance tests pass

### 9.1 Compliance Test Suite

**Test 1: Execution Isolation**

**Setup**:
- Execute workflow W with execution_id = "exec_1"
- Execute same workflow W with execution_id = "exec_2"

**Action**: Inspect resources allocated for each execution

**Expected**:
- Execution 1 and Execution 2 have **distinct resource instances**
- Resource handles (connections, clients) are NOT shared between executions

**Pass Criteria**:
- ✅ exec_1 resources ≠ exec_2 resources
- ✅ No shared connection or client detected

**Rationale**: Proves execution-level isolation.

---

**Test 2: Intra-Execution Pooling**

**Setup**:
- Workflow with 3 nodes all accessing same database (connection_id = 5)
- Single execution

**Action**: Execute workflow, monitor resource allocation

**Expected**:
- **One database connection** created
- **Same connection instance** reused by all 3 nodes
- Connection tagged with execution_id

**Pass Criteria**:
- ✅ Only 1 connection created (not 3)
- ✅ All 3 nodes use same connection handle
- ✅ Connection lifetime = execution lifetime

**Rationale**: Proves resource pooling within execution works (performance optimization).

---

**Test 3: Cross-Execution Reuse Rejection**

**Setup**:
- Execute workflow W1 → creates resource R1
- Workflow W1 completes
- Execute workflow W2 (same tenant, same workflow definition)

**Action**: Attempt to reuse resource R1 from W1 in W2

**Expected**:
- System MUST **reject reuse** or **create new resource** R2
- R1 MUST be disposed after W1 completes
- W2 uses R2 (distinct from R1)

**Pass Criteria**:
- ✅ R1 destroyed after W1
- ✅ W2 creates new resource R2
- ✅ No cross-execution reuse

**Rationale**: Proves no resource leakage between executions.

---

**Test 4: Failure Cleanup**

**Setup**:
- Workflow with 2 nodes
- Node 1 allocates database connection
- Node 2 throws exception (failure)

**Action**: Force execution failure at Node 2

**Expected**:
- Execution fails
- Database connection from Node 1 MUST be **closed/disposed**
- Resource cleanup occurs **despite failure**

**Pass Criteria**:
- ✅ Execution marked as FAILED
- ✅ Connection closed (verifiable via database server logs)
- ✅ No leaked connections

**Rationale**: Proves cleanup happens even on failure (no resource leaks).

---

**Test 5: Tenant + Execution Isolation (Cross-Check NORP-002)**

**Setup**:
- Tenant A executes workflow → Execution 1
- Tenant A executes another workflow → Execution 2 (same tenant, different execution)

**Action**: Verify resources not shared between Execution 1 and Execution 2

**Expected**:
- Resources scoped by **tenant_id AND execution_id**
- Execution 1 resources ≠ Execution 2 resources

**Pass Criteria**:
- ✅ Resources tagged with both `tenant_id` and `execution_id`
- ✅ No sharing despite same tenant

**Rationale**: Proves double isolation (NORP-002 tenant + NORP-006 execution).

Full test specifications available in `compliance-tests/NORP-006-tests.md`.

---

## 10. Security Considerations

Improper resource pooling can result in:
- **Credential leakage** (API token from Exec 1 accessible to Exec 2)
- **Cross-tenant data access** (Connection authenticated as Tenant A reused for Tenant B)
- **Replay attacks** (Authenticated session reused maliciously)
- **State contamination** (Cache from Exec 1 pollutes Exec 2 results)

**Execution-scoped isolation is a security requirement**, not just performance optimization.

**Isolation MUST take precedence over performance.**

---

## 11. Rationale Summary

**Core Principle**: Resources are bound to a single execution and MUST NOT outlive execution boundaries.

Resource pooling improves performance only when bounded by strict isolation guarantees.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 12. Future Extensions

Future NORP specifications MAY define:
- Connection pool management across executions (with strict isolation)
- Resource quotas and limits per execution
- Distributed execution context across multiple servers
- Warm resource pools with tenant-scoped pre-initialization

---

## 13. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- Resource lifecycle management patterns
- Connection pooling best practices
- OWASP Resource Injection Prevention

---

## 14. Acknowledgments

This specification is derived from ContextManager implementation in NeuraScope (production-tested with 10,000+ executions).

The authors thank reviewers for feedback on resource pooling semantics and isolation guarantees.

---

## Appendix A: Example Execution Context Pattern

### Full ExecutionContext Implementation (Python)

```python
from dataclasses import dataclass
from typing import Dict, Any, Optional
import uuid

@dataclass
class ExecutionContext:
    execution_id: str
    tenant_id: str
    blueprint_id: str
    inputs: Dict[str, Any]

    def __post_init__(self):
        self._resources: Dict[str, Any] = {}

    def get_resource(self, resource_type: str, resource_id: int):
        """
        Lazy load resource with execution scoping.
        Pooling within execution: same resource_id returns same instance.
        """
        key = f"{resource_type}:{resource_id}"

        if key not in self._resources:
            # Load resource (DB, API, model session)
            self._resources[key] = self._load_resource(resource_type, resource_id)

            print(f"[{self.execution_id}] Resource allocated: {key}")

        return self._resources[key]

    def _load_resource(self, resource_type: str, resource_id: int):
        # Validate tenant ownership (NORP-002)
        # Load connection/client based on type
        if resource_type == 'database':
            return create_db_connection(resource_id, self.tenant_id)
        elif resource_type == 'api':
            return create_api_client(resource_id, self.tenant_id)
        else:
            raise ValueError(f"Unknown resource type: {resource_type}")

    def cleanup(self):
        """
        Dispose all resources at execution end.
        MUST be called in finally block.
        """
        for key, resource in self._resources.items():
            if hasattr(resource, 'close'):
                resource.close()
                print(f"[{self.execution_id}] Resource disposed: {key}")

        self._resources.clear()
```

---

### Usage Example

```python
# Execution 1
try:
    ctx1 = ExecutionContext(
        execution_id=str(uuid.uuid4()),
        tenant_id="acme",
        blueprint_id="bp_123",
        inputs={"x": 5}
    )

    # Node 1 allocates DB connection
    db = ctx1.get_resource('database', 5)  # Creates connection

    # Node 2 reuses same connection (pooling)
    db = ctx1.get_resource('database', 5)  # ✅ Reuses (same execution)

finally:
    ctx1.cleanup()  # ✅ Always cleanup

# Execution 2 (different execution, same tenant)
try:
    ctx2 = ExecutionContext(
        execution_id=str(uuid.uuid4()),  # New execution ID
        tenant_id="acme",  # Same tenant
        blueprint_id="bp_123",
        inputs={"x": 10}
    )

    # Node 1 allocates NEW connection (no reuse from ctx1)
    db = ctx2.get_resource('database', 5)  # ✅ Creates NEW connection

finally:
    ctx2.cleanup()
```

**Result**:
- Execution 1: 1 connection created, reused across nodes, disposed
- Execution 2: NEW connection created (no reuse from Exec 1), disposed

---

## Appendix B: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | 2026-01-09 | Status upgraded to Stable. Added execution identity (5.1), execution-scoped lifetime (5.2), pooling rules (5.3), cleanup guarantees (5.4.1), failure semantics (5.5), security considerations (Section 6), observability guidance (Section 7), anti-patterns (8.1), resource lifecycle pattern (8.2), compliance tests (9.1), full ExecutionContext example (Appendix A), NORP-001/002 linkage (3.1). |
| 1.0 | 2026-01-07 | Initial draft. |

---

## Citation

```bibtex
@techreport{norp006-2026,
  title={{NORP-006: Execution Context Isolation and Resource Lifetime Management}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/NORP-006},
  license={CC BY 4.0}
}
```

---

**NORP-006 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
