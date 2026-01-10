# NORP-008
## NORP Interface Specification

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Draft

### Category
Interoperability and Machine-Readable Interfaces

### Version
1.0

### Date
2026-01-10

### Authors
NORP Working Group

---

## 1. Abstract

This specification defines a machine-readable interface for AI orchestration systems to declare, expose, and enable automated verification of their conformance to NORP specifications (NORP-001 through NORP-007).

It specifies a JSON-based declarative interface that orchestrators MUST implement to expose their capabilities, guarantees, and compliance status in a vendor-neutral, language-agnostic format.

The objective is to enable automated audit, interoperability between heterogeneous orchestration platforms, and tooling-driven governance without requiring code inspection or manual certification.

---

## 2. Motivation

### 2.1 Insufficiency of Textual Specifications for Automation

NORP specifications (NORP-001 through NORP-007) define normative requirements in RFC-style Markdown. These specifications serve their purpose: they establish architectural invariants that humans must understand and implement.

However, textual specifications cannot directly support:

**Automated audit**: A compliance tool cannot parse "The system MUST reject cyclic graphs" to verify whether a given orchestrator actually does so. A machine-readable representation of system state is required.

**Tool-driven conformance**: Certifying that an orchestrator is "NORP-001 compliant" currently requires human auditors to read source code, execute manual tests, and produce reports. This process does not scale across vendors, versions, or deployment environments.

**Platform interoperability**: When Orchestrator A (Python-based) and Orchestrator B (Go-based) both claim NORP compliance, there exists no mechanical means to compare their respective guarantees, algorithms, or limitations.

**Multi-tool governance**: A FinOps tool seeking to block workflow execution when estimated cost exceeds a threshold must understand each orchestrator's internal cost estimation API. No standardized interface exists for querying "what is the estimated cost of this workflow?"

### 2.2 NORP Interface as Structural Projection

NORP Interface is not an "export" of Markdown specifications to JSON.

It is a **structural projection** of NORP invariants into a machine-interrogable format.

**Analogy**:
- HTTP specification (RFC 7230) defines protocol rules (normative prose)
- OpenAPI Specification exposes API contracts (interrogable schema)

Similarly:
- NORP specs (Markdown) define orchestration safety rules
- **NORP Interface (JSON)** exposes orchestrator state, guarantees, and decisions

A NORP-compliant orchestrator MUST expose a JSON interface describing:
- Which NORP specifications it implements
- What validation state a given workflow is in
- What guarantees it provides (determinism, isolation, cost control)

---

## 3. Scope

### 3.1 In Scope

This specification defines:

- **JSON schema** for declaring orchestrator capabilities and NORP compliance
- **Semantic structure** of conformance declarations
- **Conceptual endpoints** for exposing NORP interface
- **Projection rules** mapping each NORP spec (001-007) to JSON representation
- **Partial compliance** declaration mechanisms

### 3.2 Out of Scope

This specification does NOT define:

- **Implementation details** of orchestrators (how validation is performed internally)
- **Execution runtime** (how workflows are physically executed)
- **Transport protocol** (HTTP, gRPC, file-based - implementer choice)
- **Authentication or authorization** for interface access
- **Workflow definition format** (NORP Interface describes guarantees, not workflows)

This specification applies to **statically defined workflows** where NORP-001 through NORP-007 are applicable.

Dynamic workflow generation or runtime graph mutation interface exposure is OUT OF SCOPE for this version.

---

## 4. Terminology

**NORP Interface**: A JSON-structured declaration of an orchestrator's capabilities, compliance status, and operational guarantees with respect to NORP specifications.

**Orchestrator**: Any system that executes AI or agent-based workflows (e.g., LangChain, n8n, Airflow, NeuraScope, custom engines).

**Capability**: A declared feature or guarantee provided by an orchestrator (e.g., "cycle detection using DFS", "deterministic ordering with lexicographic tie-breaking").

**Compliance Declaration**: A boolean assertion that an orchestrator implements a specific NORP specification (`"norp_001_compliant": true`).

**Control Plane**: The layer responsible for validation, governance, audit, and decision-making. NORP Interface operates at the control plane.

**Data Plane**: The layer responsible for physical execution of workflows (LLM calls, API invocations, database queries). NORP Interface does not operate at the data plane.

**Projection**: The mapping of a NORP specification's normative requirements into a JSON structure describing compliance status and capabilities.

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Conceptual Model

### 5.1 Control Plane vs Data Plane

NORP Interface operates at the **control plane**, not the data plane.

```
┌───────────────────────────────────────────────┐
│  Control Plane (NORP Interface)              │
│  - Validates workflow structure              │
│  - Verifies isolation constraints            │
│  - Estimates costs                           │
│  - Exposes JSON diagnostics                  │
│  - Does NOT execute workflows                │
└──────────────────┬────────────────────────────┘
                   │ Constrains
                   ↓
┌───────────────────────────────────────────────┐
│  Data Plane (Orchestrator Execution)         │
│  - Executes workflows                        │
│  - Calls LLMs, APIs, databases               │
│  - Manages runtime state                     │
│  - Reports results to control plane          │
└───────────────────────────────────────────────┘
```

NORP Interface **describes** what the control plane guarantees.
It does **not** implement the data plane.

---

### 5.2 Declaration vs Proof

**Declaration** (via NORP Interface):
An orchestrator exposes `/norp-interface.json` stating:
```json
{"norp_002_multi_tenant": {"compliant": true}}
```

This is a **claim**, not proof.

**Proof** (via NORP Compliance Tests):
The orchestrator passes `NORP-002-tests.md` Test 2:
```
Test 2: Cross-Tenant Resource Rejection
Setup: Resource R owned by Tenant A, execution context = Tenant B
Expected: Rejection with PERMISSION_ERROR
Actual: ✅ REJECTED
```

This is **evidence** of compliance.

**NORP Interface facilitates discovery and automation.**
**NORP Compliance Tests provide verification.**

Both are necessary. Declaration without proof is untrustworthy. Proof without declaration is undiscoverable.

---

### 5.3 Partial Compliance as First-Class Concept

An orchestrator need not implement all seven NORP specifications to provide value.

NORP Interface MUST allow **explicit partial compliance** declaration:

```json
{
  "norp_version": "1.2",
  "compliance": {
    "NORP-001": true,
    "NORP-002": false,
    "NORP-003": true,
    "NORP-004": true,
    "NORP-005": true,
    "NORP-006": false,
    "NORP-007": false
  },
  "non_compliance_rationale": {
    "NORP-002": "Single-tenant system, multi-tenant isolation not applicable",
    "NORP-006": "Stateless execution, no resource pooling",
    "NORP-007": "All models local (MLX), zero API cost"
  }
}
```

This transparency enables:
- **Informed selection**: Clients can choose orchestrators based on declared capabilities
- **Progressive adoption**: Orchestrators can implement NORP incrementally
- **Honest communication**: "Not applicable" ≠ "non-compliant"

---

## 6. Normative Requirements

### 6.1 NORP Interface Structure

Every NORP-compliant orchestrator MUST expose a JSON structure conforming to the schema defined in this specification.

The interface MUST include:
- **Version declaration** (`norp_version`)
- **Orchestrator metadata** (`orchestrator` object)
- **Global compliance status** (`compliance` object)
- **Per-NORP capability declarations** (7 sections, one per NORP spec)

The interface MAY be exposed via:
- HTTP endpoint (e.g., `GET /norp-interface.json`)
- File in repository (e.g., `.norp-interface.json`)
- Embedded in orchestrator binary (queryable via CLI)

The transport mechanism is **not specified** by this document.

---

### 6.2 Versioning

The NORP Interface MUST declare which version of NORP specifications it conforms to.

```json
{
  "norp_version": "1.2",
  "norp_interface_version": "1.0"
}
```

**Versioning rules**:
- `norp_version` refers to the **highest NORP spec version** implemented
- `norp_interface_version` refers to THIS specification (NORP-008) version
- If NORP-001 v1.2 and NORP-002 v1.0 are implemented, declare `"norp_version": "1.2"`
- Breaking changes in NORP specs increment major version (e.g., 1.x → 2.0)

---

### 6.3 Orchestrator Metadata

The interface MUST include identifying metadata about the orchestrator.

**Required fields**:
```json
{
  "orchestrator": {
    "name": "string",
    "version": "string"
  }
}
```

**Optional fields**:
```json
{
  "orchestrator": {
    "vendor": "string",
    "language": "string",
    "repository": "string (URL)",
    "documentation": "string (URL)",
    "license": "string"
  }
}
```

---

### 6.4 Global Compliance Declaration

The interface MUST include a global compliance summary.

```json
{
  "compliance": {
    "NORP-001": boolean,
    "NORP-002": boolean,
    "NORP-003": boolean,
    "NORP-004": boolean,
    "NORP-005": boolean,
    "NORP-006": boolean,
    "NORP-007": boolean
  }
}
```

If `false`, the corresponding per-NORP section MAY be omitted OR MAY include a `compliant: false` with `rationale` field.

---

### 6.5 Per-NORP Capability Declarations

For each NORP specification declared as `compliant: true`, the interface MUST include a corresponding section detailing capabilities.

#### 6.5.1 NORP-001 Projection (Validation Pipeline)

```json
{
  "norp_001_validation_pipeline": {
    "compliant": boolean,
    "stages": array<string>,
    "stage_order": "strict" | "flexible",
    "fail_fast": boolean,
    "caching": boolean (optional)
  }
}
```

**Required if compliant**: `stages`, `stage_order`, `fail_fast`

**Example**:
```json
{
  "norp_001_validation_pipeline": {
    "compliant": true,
    "stages": ["structural_validation", "compilation", "context_resolution", "execution", "aggregation", "accounting"],
    "stage_order": "strict",
    "fail_fast": true,
    "caching": true
  }
}
```

---

#### 6.5.2 NORP-002 Projection (Multi-Tenant Isolation)

```json
{
  "norp_002_multi_tenant": {
    "compliant": boolean,
    "tenant_model": "organization" | "user" | "hierarchical" | "not_applicable",
    "resolution_algorithm": array<string>,
    "scoping_mechanisms": array<string>,
    "global_resources_supported": boolean (optional),
    "cross_tenant_collaboration": boolean (optional)
  }
}
```

**Required if compliant**: `tenant_model`, `resolution_algorithm`, `scoping_mechanisms`

**Example**:
```json
{
  "norp_002_multi_tenant": {
    "compliant": true,
    "tenant_model": "organization",
    "resolution_algorithm": ["execution_context", "principal_identity", "workflow_ownership"],
    "scoping_mechanisms": ["database_filters", "tenant_prefixed_paths", "cache_keys"],
    "global_resources_supported": true,
    "cross_tenant_collaboration": true
  }
}
```

If single-tenant:
```json
{
  "norp_002_multi_tenant": {
    "compliant": false,
    "tenant_model": "not_applicable",
    "rationale": "Single-tenant deployment"
  }
}
```

---

#### 6.5.3 NORP-003 Projection (Immutable State)

```json
{
  "norp_003_immutability": {
    "compliant": boolean,
    "dto_mechanism": "readonly" | "frozen_dataclass" | "object_freeze" | "final_fields" | "other",
    "language": string,
    "deep_immutability": boolean,
    "serialization_supported": boolean (optional)
  }
}
```

**Required if compliant**: `dto_mechanism`, `deep_immutability`

**Example**:
```json
{
  "norp_003_immutability": {
    "compliant": true,
    "dto_mechanism": "readonly",
    "language": "PHP 8.2",
    "deep_immutability": true,
    "serialization_supported": true
  }
}
```

---

#### 6.5.4 NORP-004 Projection (Cycle Detection)

```json
{
  "norp_004_cycle_detection": {
    "compliant": boolean,
    "algorithm": "DFS" | "Kahn" | "other",
    "complexity": "O(V+E)" | "O(V^2)" | "other",
    "disconnected_subgraphs": boolean,
    "diagnostic_completeness": "all_cycles" | "first_cycle"
  }
}
```

**Required if compliant**: `algorithm`, `complexity`, `disconnected_subgraphs`

**Example**:
```json
{
  "norp_004_cycle_detection": {
    "compliant": true,
    "algorithm": "DFS",
    "complexity": "O(V+E)",
    "disconnected_subgraphs": true,
    "diagnostic_completeness": "all_cycles"
  }
}
```

---

#### 6.5.5 NORP-005 Projection (Deterministic Ordering)

```json
{
  "norp_005_deterministic_ordering": {
    "compliant": boolean,
    "algorithm": "Kahn" | "DFS_postorder" | "other",
    "tie_breaking": "lexicographic" | "priority" | "insertion_order" | "other",
    "parallel_groups": boolean (optional),
    "logical_vs_physical_distinction": boolean (optional)
  }
}
```

**Required if compliant**: `algorithm`, `tie_breaking`

**Example**:
```json
{
  "norp_005_deterministic_ordering": {
    "compliant": true,
    "algorithm": "Kahn",
    "tie_breaking": "lexicographic",
    "parallel_groups": true,
    "logical_vs_physical_distinction": true
  }
}
```

---

#### 6.5.6 NORP-006 Projection (Resource Pooling)

```json
{
  "norp_006_resource_pooling": {
    "compliant": boolean,
    "execution_scoped": boolean,
    "cleanup_mechanism": "try_finally" | "defer" | "RAII" | "other",
    "pooling_supported": boolean,
    "resource_types": array<string> (optional)
  }
}
```

**Required if compliant**: `execution_scoped`, `cleanup_mechanism`

**Example**:
```json
{
  "norp_006_resource_pooling": {
    "compliant": true,
    "execution_scoped": true,
    "cleanup_mechanism": "try_finally",
    "pooling_supported": true,
    "resource_types": ["database", "api_client", "cache"]
  }
}
```

---

#### 6.5.7 NORP-007 Projection (Cost Estimation)

```json
{
  "norp_007_cost_control": {
    "compliant": boolean,
    "estimation_method": "native_tokenizer" | "approximation" | "not_applicable",
    "conservative_margin": number (0.0 to 1.0),
    "budget_levels": array<string>,
    "runtime_enforcement": boolean,
    "pricing_source": "hardcoded" | "dynamic_api" | "not_applicable"
  }
}
```

**Required if compliant**: `estimation_method`, `budget_levels`

**Example** (compliant):
```json
{
  "norp_007_cost_control": {
    "compliant": true,
    "estimation_method": "approximation",
    "conservative_margin": 0.30,
    "budget_levels": ["per_execution", "per_tenant_daily"],
    "runtime_enforcement": false,
    "pricing_source": "hardcoded"
  }
}
```

**Example** (not applicable - local models):
```json
{
  "norp_007_cost_control": {
    "compliant": false,
    "estimation_method": "not_applicable",
    "rationale": "All LLM models local (Ollama), zero API cost"
  }
}
```

---

### 6.6 Non-Compliance Rationale

When `compliant: false` for any NORP specification, the interface SHOULD include a `non_compliance_rationale` object.

```json
{
  "non_compliance_rationale": {
    "NORP-002": "Single-tenant deployment, multi-tenant isolation not applicable",
    "NORP-007": "All LLM models local (MLX/Ollama), zero API cost"
  }
}
```

Valid rationale categories:
- **"Not applicable"**: System design makes NORP irrelevant (e.g., single-tenant for NORP-002)
- **"Not implemented"**: NORP is applicable but not yet implemented (planned for future)
- **"Partial implementation"**: Some requirements met, not all (with details)

---

## 7. Conceptual Endpoints (Non-Normative)

This section describes **conceptual endpoints** an orchestrator MAY implement. The exact API design, authentication, and transport protocol are OUT OF SCOPE.

### 7.1 GET /norp-interface.json

**Purpose**: Expose global capabilities and compliance status

**Response schema**:
```json
{
  "norp_version": "1.2",
  "norp_interface_version": "1.0",
  "orchestrator": { ... },
  "compliance": { ... },
  "norp_001_validation_pipeline": { ... },
  "norp_002_multi_tenant": { ... },
  ...
}
```

---

### 7.2 POST /norp/validate

**Purpose**: Validate a workflow and return NORP-001 compliant validation result

**Request**:
```json
{
  "workflow": { "nodes": [ ... ] }
}
```

**Response**:
```json
{
  "norp_001_validation_result": {
    "valid": boolean,
    "stage": "structural_validation" | "compilation" | "context_resolution",
    "errors": array<error_object>,
    "execution_prevented": boolean,
    "timestamp": "ISO8601"
  }
}
```

---

### 7.3 POST /norp/estimate-cost

**Purpose**: Estimate workflow cost per NORP-007

**Request**:
```json
{
  "workflow": { "nodes": [ ... ] }
}
```

**Response**:
```json
{
  "norp_007_cost_estimation": {
    "estimated_cost_usd": number,
    "conservative_margin": number,
    "breakdown": array<node_cost>,
    "budget_usd": number,
    "enforcement_decision": "ALLOWED" | "REJECTED" | "REQUIRES_OVERRIDE"
  }
}
```

---

### 7.4 GET /norp/executions/{execution_id}/context

**Purpose**: Retrieve isolation context for completed execution

**Response**:
```json
{
  "execution_context": {
    "execution_id": "string",
    "tenant_id": "string",
    "norp_002_tenant_isolation": { ... },
    "norp_006_execution_isolation": { ... }
  }
}
```

---

## 8. Full Interface Example (Normative)

### 8.1 Multi-Tenant SaaS Orchestrator (Fully Compliant)

```json
{
  "norp_version": "1.2",
  "norp_interface_version": "1.0",
  "orchestrator": {
    "name": "NeuraScope Blueprint Engine",
    "version": "2.5.0",
    "vendor": "NeuraScope CONVERWAY",
    "language": "PHP 8.2",
    "repository": "https://github.com/neurascope/blueprint-engine",
    "license": "Proprietary"
  },
  "compliance": {
    "NORP-001": true,
    "NORP-002": true,
    "NORP-003": true,
    "NORP-004": true,
    "NORP-005": true,
    "NORP-006": true,
    "NORP-007": true
  },
  "norp_001_validation_pipeline": {
    "compliant": true,
    "stages": ["structural_validation", "compilation", "context_resolution", "execution", "aggregation", "accounting"],
    "stage_order": "strict",
    "fail_fast": true,
    "caching": true
  },
  "norp_002_multi_tenant": {
    "compliant": true,
    "tenant_model": "organization",
    "resolution_algorithm": ["execution_context", "principal_identity", "workflow_ownership"],
    "scoping_mechanisms": ["database_filters", "tenant_prefixed_paths", "cache_keys"],
    "global_resources_supported": true,
    "cross_tenant_collaboration": true
  },
  "norp_003_immutability": {
    "compliant": true,
    "dto_mechanism": "readonly",
    "language": "PHP 8.2",
    "deep_immutability": true,
    "serialization_supported": true
  },
  "norp_004_cycle_detection": {
    "compliant": true,
    "algorithm": "DFS",
    "complexity": "O(V+E)",
    "disconnected_subgraphs": true,
    "diagnostic_completeness": "all_cycles"
  },
  "norp_005_deterministic_ordering": {
    "compliant": true,
    "algorithm": "Kahn",
    "tie_breaking": "lexicographic",
    "parallel_groups": true,
    "logical_vs_physical_distinction": true
  },
  "norp_006_resource_pooling": {
    "compliant": true,
    "execution_scoped": true,
    "cleanup_mechanism": "try_finally",
    "pooling_supported": true,
    "resource_types": ["database", "api_client", "cache"]
  },
  "norp_007_cost_control": {
    "compliant": true,
    "estimation_method": "approximation",
    "conservative_margin": 0.30,
    "budget_levels": ["per_execution", "per_tenant_daily"],
    "runtime_enforcement": false,
    "pricing_source": "hardcoded"
  }
}
```

---

### 8.2 Partial Compliance Example (Single-Tenant, Local Models)

```json
{
  "norp_version": "1.2",
  "norp_interface_version": "1.0",
  "orchestrator": {
    "name": "LocalFlow Engine",
    "version": "1.0.0",
    "language": "Python 3.11",
    "license": "MIT"
  },
  "compliance": {
    "NORP-001": true,
    "NORP-002": false,
    "NORP-003": true,
    "NORP-004": true,
    "NORP-005": true,
    "NORP-006": false,
    "NORP-007": false
  },
  "non_compliance_rationale": {
    "NORP-002": "Single-tenant system, multi-tenant isolation not applicable",
    "NORP-006": "Stateless execution model, no persistent resource handles",
    "NORP-007": "All models local (Ollama), zero API cost, estimation not applicable"
  },
  "norp_001_validation_pipeline": {
    "compliant": true,
    "stages": ["structural_validation", "compilation", "execution"],
    "stage_order": "strict",
    "fail_fast": true
  },
  "norp_003_immutability": {
    "compliant": true,
    "dto_mechanism": "frozen_dataclass",
    "language": "Python 3.11",
    "deep_immutability": true
  },
  "norp_004_cycle_detection": {
    "compliant": true,
    "algorithm": "Kahn",
    "complexity": "O(V+E)",
    "disconnected_subgraphs": true,
    "diagnostic_completeness": "first_cycle"
  },
  "norp_005_deterministic_ordering": {
    "compliant": true,
    "algorithm": "Kahn",
    "tie_breaking": "insertion_order",
    "parallel_groups": false
  }
}
```

**Transparency**: Explicitly declares which NORP specs are not applicable and why.

---

## 9. Validation Result Structures (Non-Normative)

### 9.1 NORP-001 Validation Result

When an orchestrator validates a workflow, it SHOULD return a structure conforming to:

```json
{
  "validation_result": {
    "norp_001_compliant": true,
    "workflow_id": "string",
    "valid": boolean,
    "rejected_at_stage": "structural_validation" | "compilation" | "context_resolution" | null,
    "errors": [
      {
        "norp_spec": "NORP-004" | "NORP-001" | ...,
        "type": "STRUCTURAL_ERROR" | "RESOURCE_ERROR" | "PERMISSION_ERROR" | "COST_ERROR",
        "code": "string",
        "message": "string",
        "location": object (optional)
      }
    ],
    "warnings": array<string> (optional),
    "execution_prevented": boolean,
    "timestamp": "ISO8601"
  }
}
```

---

### 9.2 NORP-007 Cost Estimation Result

```json
{
  "cost_estimation": {
    "norp_007_compliant": true,
    "workflow_id": "string",
    "estimated_cost_usd": number,
    "conservative_margin": number,
    "breakdown": [
      {
        "node_id": "string",
        "type": "llm_call" | "api_call" | ...,
        "model": "string",
        "input_tokens_estimated": number,
        "output_tokens_max": number,
        "cost_usd": number
      }
    ],
    "budget_usd": number,
    "enforcement_decision": "ALLOWED" | "REJECTED" | "REQUIRES_OVERRIDE",
    "timestamp": "ISO8601"
  }
}
```

---

### 9.3 NORP-002/006 Execution Context

```json
{
  "execution_context": {
    "execution_id": "string",
    "tenant_id": "string",
    "started_at": "ISO8601",
    "completed_at": "ISO8601",
    "status": "SUCCESS" | "FAILED" | "RUNNING",
    "norp_002_tenant_isolation": {
      "tenant_resolution": {
        "source": "execution_context" | "principal_identity" | "workflow_ownership",
        "tenant_id": "string",
        "verified_at": "context_resolution"
      },
      "resources_accessed": [
        {
          "type": "database" | "llm_server" | ...,
          "id": number | string,
          "tenant_verified": boolean,
          "global": boolean
        }
      ]
    },
    "norp_006_execution_isolation": {
      "execution_id_unique": true,
      "resources_allocated": [
        {
          "type": "database_connection" | "api_client" | ...,
          "id": "string",
          "pooled": boolean
        }
      ],
      "cleanup_completed": boolean,
      "cross_execution_reuse": false
    }
  }
}
```

---

## 10. Relationship to MCP

### 10.1 Orthogonal Concerns

**MCP (Model Context Protocol)** and **NORP** address different architectural layers:

| Aspect | MCP | NORP |
|--------|-----|------|
| **Domain** | LLM ↔ Tools/Context communication | Orchestration governance |
| **Function** | Standardize **how** LLM accesses resources (tools, memory, files) | Standardize **guarantees** around execution (validation, isolation, costs) |
| **Protocol** | JSON-RPC 2.0 (request/response for tool invocation) | Declarative JSON (capabilities and compliance) |
| **Layer** | Data plane (runtime tool execution) | Control plane (pre-execution validation, audit) |
| **Example operation** | `memory_recall`, `file_search`, `tool_execution` | "Cycle detection O(V+E)", "Cost $2.50 estimated", "Tenant acme_corp isolated" |

---

### 10.2 Coexistence in Same System

A modern orchestrator can be **both** MCP-compliant and NORP-compliant:

```json
{
  "protocols": {
    "mcp": {
      "version": "1.0",
      "endpoint": "https://api.example.com/mcp",
      "tools_exposed": ["memory_recall", "file_search", "code_execution"]
    },
    "norp": {
      "version": "1.2",
      "endpoint": "https://api.example.com/norp-interface.json",
      "compliance": ["NORP-001", "NORP-002", "NORP-005", "NORP-007"]
    }
  }
}
```

**MCP handles**: LLM tool invocations (data plane)
**NORP handles**: Workflow validation, isolation, cost governance (control plane)

**No overlap**. Both protocols are complementary.

---

## 11. Security and Limitations

### 11.1 Interface Can Lie

NORP Interface is **declarative**, not **enforceable**.

An orchestrator can declare:
```json
{"norp_002_multi_tenant": {"compliant": true}}
```

...while failing to actually enforce tenant isolation.

**NORP Interface does not prevent false declarations.**

---

### 11.2 Compliance Tests Remain Mandatory

**Declaration** via NORP Interface **facilitates discovery and automation**.

**Proof** via NORP Compliance Tests (NORP-001-tests.md through NORP-007-tests.md) **provides verification**.

**Certification process**:
1. Orchestrator declares compliance via `/norp-interface.json`
2. Auditor executes relevant compliance test suites
3. If all tests pass → Compliance **verified**
4. If tests fail → Compliance declaration **invalid**

NORP Interface is **not a trust mechanism**. It is a **standardized discovery and query mechanism**.

---

### 11.3 Assumed Threat Model

Implementers of NORP Interface SHOULD assume:
- **Malicious actors** may craft false interface declarations
- **Interface may be stale** (orchestrator updated, interface not regenerated)
- **Interface may be incomplete** (required fields missing)

Tools consuming NORP Interface MUST:
- **Validate JSON schema** conformance before trusting content
- Treat compliance declarations as **untrusted** until verified via tests
- Implement **graceful degradation** if interface unavailable or invalid

---

## 12. Interoperability Use Cases

### 12.1 Automated Multi-Tenant Audit

**Tool**: Security compliance scanner

**Workflow**:
1. Discover orchestrators in infrastructure
2. Query each `/norp-interface.json`
3. Filter for `compliance.NORP-002 == true`
4. For each, execute `NORP-002-tests.md` test suite
5. Generate compliance report

**Enabled by NORP Interface**: Automated discovery of multi-tenant capable orchestrators without manual inventory.

---

### 12.2 FinOps Pre-Execution Budget Gate

**Tool**: Cost governance platform

**Workflow**:
1. Intercept workflow submission
2. Check orchestrator `/norp-interface.json` for `compliance.NORP-007`
3. If true, call `/norp/estimate-cost`
4. Compare `estimated_cost_usd` vs organization budget policy
5. If exceeded, reject **before** orchestrator executes

**Enabled by NORP Interface**: Pre-execution cost control without orchestrator-specific SDK integration.

---

### 12.3 Orchestrator Selection Based on Capabilities

**Scenario**: Enterprise selecting orchestrator for HIPAA-compliant workload

**Requirements**:
- NORP-002 (multi-tenant isolation): **MANDATORY**
- NORP-007 (cost control): **MANDATORY**
- NORP-001 (validation): **RECOMMENDED**

**Automated qualification**:
```bash
for orch in candidate_orchestrators; do
  compliance=$(curl $orch/norp-interface.json | jq '.compliance')
  if echo $compliance | jq -e '.["NORP-002"] and .["NORP-007"]' > /dev/null; then
    echo "$orch: QUALIFIED"
  else
    echo "$orch: REJECTED (missing required NORP compliance)"
  fi
done
```

**Enabled by NORP Interface**: Procurement automation based on declared capabilities.

---

### 12.4 CI/CD NORP Compliance Regression Detection

**Scenario**: Prevent deployment if orchestrator loses NORP compliance

**GitHub Actions**:
```yaml
- name: Verify NORP Compliance
  run: |
    curl $ORCHESTRATOR_URL/norp-interface.json > current.json
    norp-validator --check current.json --require NORP-002,NORP-007
    if [ $? -ne 0 ]; then
      echo "❌ NORP compliance regression detected"
      exit 1
    fi
```

**Enabled by NORP Interface**: Continuous compliance verification in deployment pipeline.

---

## 13. Tooling Ecosystem (Non-Normative)

NORP Interface enables vendor-neutral tooling:

### 13.1 norp-validator

**Function**: Validate NORP Interface JSON against schema

```bash
norp-validator --check norp-interface.json
→ ✅ Valid JSON
→ ✅ Schema conformant
→ ⚠️  NORP-002 declared true but scoping_mechanisms field missing
```

---

### 13.2 norp-compare

**Function**: Compare capabilities across orchestrators

```bash
norp-compare orch-a.json orch-b.json --diff NORP-005
→ Both NORP-005 compliant
→ A: tie_breaking = "lexicographic"
→ B: tie_breaking = "insertion_order"
→ ⚠️  Workflows may produce different orders when migrating A→B
```

---

### 13.3 norp-audit

**Function**: Generate compliance audit report

```bash
norp-audit --orchestrators urls.txt --require NORP-002,NORP-007
→ Generates HTML report with compliance matrix
```

---

### 13.4 norp-badge

**Function**: Generate compliance badges

```bash
norp-badge --interface norp-interface.json
→ ![NORP-001](https://img.shields.io/badge/NORP--001-Compliant-green)
→ ![NORP-007](https://img.shields.io/badge/NORP--007-Compliant-green)
```

---

## 14. Compliance

A system is **NORP-008 compliant** if:
- It exposes a JSON interface conforming to Section 6 schema
- All required fields are present for declared compliant NORP specs
- JSON is valid and schema-conformant
- Interface is accessible (via HTTP, file, or CLI)

### 14.1 Compliance Test Suite

**Test 1: Schema Validity**
- Fetch `/norp-interface.json`
- Validate against `norp-interface.schema.json`
- Expected: Valid JSON Schema

**Test 2: Required Fields Present**
- If `compliance.NORP-001 == true`, verify `norp_001_validation_pipeline` section exists
- Repeat for all 7 NORP specs
- Expected: All required sections present

**Test 3: Consistency Check**
- If `norp_005_deterministic_ordering.tie_breaking == "lexicographic"`, execute workflow twice
- Verify execution order identical
- Expected: Interface declaration matches actual behavior

---

## 15. Rationale Summary

**Core Principle**: Textual specifications define rules for humans. Machine-readable interfaces enable automation for tools.

NORP-001 through NORP-007 establish **what orchestrators must do**.
NORP-008 establishes **how orchestrators declare what they do**.

This separation enables:
- **Discoverability**: Tools find NORP-compliant orchestrators automatically
- **Automation**: Audit, governance without manual code inspection
- **Interoperability**: Heterogeneous systems expose uniform interface
- **Transparency**: Partial compliance explicitly declared, not hidden

NORP Interface transforms NORP from documentation into **interoperability layer**.

---

## 16. Future Extensions

Future versions of NORP-008 MAY define:
- **Real-time compliance monitoring** endpoints (streaming updates)
- **Capability negotiation** (client requests minimum NORP level, orchestrator confirms)
- **Federation** (aggregating interfaces across distributed orchestrators)
- **Cryptographic attestation** (signed interface for tamper-proof declarations)

Future NORP specifications (NORP-009+) will define their own interface projections extending this model.

---

## 17. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- [JSON Schema](https://json-schema.org/): JSON Schema Specification
- [OpenAPI Specification 3.0](https://spec.openapis.org/oas/v3.0.0): API interface standardization model
- [Model Context Protocol (MCP)](https://modelcontextprotocol.io): LLM tool access standardization
- [Prometheus Exposition Formats](https://prometheus.io/docs/instrumenting/exposition_formats/): Metrics exposition patterns

---

## 18. Acknowledgments

This specification synthesizes patterns from OpenAPI interface exposure, Prometheus metrics endpoints, Kubernetes Custom Resource Definitions, and production NORP implementation experience at NeuraScope.

The authors thank contributors for feedback on JSON schema design and interoperability semantics.

---

## Appendix A: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-10 | Initial draft. JSON schema, projection rules, conceptual endpoints, compliance examples, tooling ecosystem. |

---

## Appendix B: Complete JSON Schema

See `schemas/norp-interface.schema.json` for full JSON Schema definition (to be published).

**Required root properties**:
- `norp_version` (string, pattern `^\d+\.\d+$`)
- `norp_interface_version` (string)
- `orchestrator` (object with `name`, `version`)
- `compliance` (object with 7 boolean fields)

**Conditional properties**:
- If `compliance.NORP-XXX == true`, corresponding `norp_XXX_*` section REQUIRED

---

## Citation

```bibtex
@techreport{norp008-2026,
  title={{NORP-008: NORP Interface Specification}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={10},
  version={1.0},
  status={Draft},
  url={https://norp.neurascope.ai/specs/NORP-008},
  license={CC BY 4.0}
}
```

---

**NORP-008 v1.0 DRAFT**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
