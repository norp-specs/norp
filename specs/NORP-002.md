# NORP-002
## Multi-Tenant Resource Isolation for AI Orchestration Systems

---

**License**: [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/)
**Copyright**: © 2026 NeuraScope CONVERWAY
**DOI**: (To be assigned)

---

### Status
Stable

### Category
Security and Isolation Semantics

### Version
1.2

### Date
2026-01-09

### Authors
NORP Working Group

---

## 1. Abstract

This document defines mandatory isolation rules for multi-tenant AI orchestration systems.

It specifies how tenant identity MUST be resolved, how resources MUST be scoped, and how cross-tenant access MUST be prevented or explicitly authorized.

The objective is to guarantee strict data isolation, prevent privilege escalation, and ensure auditable execution boundaries.

---

## 2. Motivation

AI workflows dynamically load external resources, inject contextual data into probabilistic models, and may trigger irreversible side effects.

In multi-tenant environments, insufficient isolation can result in:
- Cross-tenant data exposure
- Unauthorized model or tool access
- Confidentiality and regulatory breaches

Tenant isolation is therefore a foundational invariant, not an implementation detail.

---

## 3. Scope

This specification applies to systems that:
- Enforce isolation between two or more tenants
- Execute workflows on behalf of distinct entities
- Resolve execution context dynamically

**Single-tenant systems** are OUT OF SCOPE.

This specification applies only to **statically defined workflows**.

---

## 4. Terminology

**Tenant**: The smallest isolation unit enforced by the system.

### 4.1 Tenant Model Consistency

Systems MUST enforce isolation at a **single, consistent level**.

Valid models include:
- **Organization-level isolation** (users share organization resources)
- **User-level isolation** (strictest, no sharing)
- **Hierarchical models** (e.g., organization with sub-teams), provided the enforced tenant boundary is explicit and consistent

**Example**: Tenant = `organization_id`, with team-level permissions within organization scope.

Systems MUST document their tenant model explicitly.

---

**Execution Context**: The resolved tenant identity and permissions under which a workflow executes.

**Resource**: Any external dependency such as an API, model endpoint, database, file storage, or tool.

The keywords MUST, SHOULD, and MAY are to be interpreted as described in [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

## 5. Normative Requirements

### 5.1 Tenant Resolution

Before any resource is accessed, the system MUST resolve a **single tenant identity** for execution.

#### 5.1.1 Tenant Resolution Algorithm

Tenant identity MUST be resolved using the following **priority order**:

1. **Explicit execution context** (e.g., API header `X-Tenant-ID`, execution parameter)
2. **Authenticated principal identity** (e.g., JWT claim, API key metadata)
3. **Workflow ownership metadata** (e.g., `created_by_tenant_id`)

Tenant resolution MUST be **idempotent**: resolving twice produces the same result.

#### 5.1.2 Conflict Resolution

If multiple sources provide **different tenant identities**:

- If a **higher-priority source** exists, it MUST be used
- If two sources **at the same priority level** conflict, execution MUST be **rejected**
- Workflow ownership MUST **NEVER override** an explicit execution context

**Examples**:

| Scenario | API Header | JWT Claim | Workflow Owner | Resolution |
|----------|------------|-----------|----------------|------------|
| 1 | `acme` | `globex` | `acme` | **REJECT** (conflict at same priority) |
| 2 | `acme` | - | `globex` | **Use `acme`** (header > ownership) |
| 3 | - | `acme` | `acme` | **Use `acme`** (consistent) |
| 4 | `acme` | `acme` | `globex` | **Use `acme`** (context > ownership) |

---

### 5.2 Mandatory Resource Scoping

All resource access MUST be **explicitly scoped** to the resolved tenant.

#### 5.2.1 Resource Scoping Mechanisms

Valid scoping mechanisms include, but are not limited to:

- **Database-level filters**: `WHERE tenant_id = ?`
- **Row-Level Security (RLS)**: PostgreSQL policies, MySQL views
- **Tenant-prefixed API paths**: `/api/tenants/{tenant_id}/resources`
- **Tenant-scoped headers**: `X-Tenant-ID: acme`
- **Tenant-prefixed file paths**: `/storage/tenants/{tenant_id}/`
- **Tenant-scoped cache keys**: `tenant:{tenant_id}:resource:{id}`

The scoping mechanism MUST guarantee that:
- Resources from other tenants **CANNOT be accessed**, even if their identifiers are known
- Removing the tenant constraint would expose cross-tenant data (proof of isolation)

Implementations using Row-Level Security or equivalent database features MUST ensure policies are enforced **at the database level**, not application level.

#### 5.2.2 Isolation Verification Test

Implementers SHOULD verify isolation correctness by:

1. Execute query **WITH tenant filter** → Result count = **N**
2. Execute same query **WITHOUT tenant filter** → Result count = **M**
3. Verify **M > N** (proves isolation is active)

**Example**:
```sql
-- WITH filter
SELECT COUNT(*) FROM datasources WHERE tenant_id = 'acme';
→ 15

-- WITHOUT filter
SELECT COUNT(*) FROM datasources;
→ 1547

-- Conclusion: 1547 > 15 → Isolation verified ✅
```

If **M == N**, either:
- Only one tenant exists (invalid test environment)
- Isolation may not be enforced (requires investigation)

---

### 5.3 No Implicit Global Access

Resources MUST NOT be globally accessible by default.

#### 5.3.1 Global Resource Criteria

A resource MAY be declared **global** only if:
- It contains **NO tenant-specific data**
- Access is **read-only** OR writes are isolated per tenant
- Access is **fully auditable**
- The resource is **explicitly marked** as global (e.g., `is_global = true`)

**Examples of valid global resources**:
- Public LLM endpoints (Claude, GPT-4) with tenant-scoped request logging
- Public APIs (weather, stock prices) with per-tenant quota tracking

**Examples of INVALID global resources**:
- Shared database with mixed tenant data
- Writable file storage without tenant prefixes
- "Default" resources accessible without explicit global flag

---

### 5.4 Isolation at Validation Time

During validation, the system MUST verify that:
- All referenced resources **exist within the tenant scope**
- The execution context has **permission** to access each resource
- Resources are **active and available**

Resources outside the tenant scope MUST be **rejected during validation**, not deferred to execution.

---

### 5.5 Isolation at Execution Time

During execution:
- All **READ operations** MUST be tenant-scoped
- Any attempt to read resources **outside the tenant scope** MUST fail immediately

Execution MUST NOT introduce broader access than what was validated.

---

### 5.6 No Cross-Tenant Side Effects

**WRITE operations** MUST NOT affect data outside the resolved tenant scope.

This includes:
- Writing to foreign tenant storage
- Triggering tools or APIs on behalf of another tenant
- Mutating shared state without explicit authorization

#### 5.6.1 Authorized Cross-Tenant Collaboration (Optional)

Cross-tenant operations are **PROHIBITED by default**.

Exceptions are permitted **only if**:
- **Explicit authorization** exists (e.g., shared resource with ACL entry)
- Authorization is **verified during Context Resolution** (not execution)
- Operations are **fully logged** with source tenant + destination tenant identifiers

Systems supporting cross-tenant collaboration MUST document:
- **Authorization model** (ACLs, RBAC, etc.)
- **Audit trail requirements**
- **Revocation mechanisms**

---

## 6. Fail-Safe Behavior

If any isolation rule cannot be verified or enforced, execution MUST be **prevented**.

Fail-safe behavior includes:
- Rejecting execution
- Producing an explicit `PERMISSION_ERROR`
- Logging the isolation violation attempt

---

## 7. Security Considerations

Workflow definitions MUST be treated as **untrusted input**.

Isolation mechanisms MUST assume **adversarial attempts** to bypass tenant boundaries.

Implementers SHOULD assume that:
- Workflow definitions may be malicious
- Resource identifiers may be probed systematically
- Timing attacks may be used to infer tenant data

---

## 8. Implementation Guidance (Non-Normative)

### 8.1 Common Anti-Patterns

#### SQL/ORM
❌ **BAD**: `SELECT * FROM resources WHERE id = ?`
✅ **GOOD**: `SELECT * FROM resources WHERE id = ? AND tenant_id = ?`

#### REST API
❌ **BAD**: `GET /api/resources/123`
✅ **GOOD**: `GET /api/tenants/{tenant_id}/resources/123`

#### Cache
❌ **BAD**: `cache.get("workflow_123")`
✅ **GOOD**: `cache.get("tenant:acme:workflow_123")`

#### File Storage
❌ **BAD**: `/storage/uploads/file.pdf`
✅ **GOOD**: `/storage/tenants/acme/uploads/file.pdf`

#### MongoDB
❌ **BAD**: `db.resources.findOne({_id: "123"})`
✅ **GOOD**: `db.resources.findOne({_id: "123", tenant_id: "acme"})`

---

### 8.2 Code Review Checklist

When reviewing code for NORP-002 compliance:

- [ ] Every database query includes tenant filter
- [ ] Every API call includes tenant identifier (header, path, or query param)
- [ ] Every file operation uses tenant-prefixed paths
- [ ] Cache keys include tenant identifiers
- [ ] No raw SQL without tenant `WHERE` clause
- [ ] No ORM queries without tenant scope
- [ ] Global resources explicitly marked (`is_global = true`)
- [ ] Cross-tenant operations have ACL verification

---

## 9. Compliance

A system is **NORP-002 compliant** if:
- All mandatory requirements (Sections 5.1–5.6) are implemented
- Tenant resolution occurs **before any resource access**
- All resource access is **tenant-scoped**
- All mandatory compliance tests **pass**

### 9.1 Compliance Test Suite

**Test 1: Tenant Conflict Rejection**
- **Setup**: Workflow owned by Tenant A, execution context = Tenant B (conflicting)
- **Expected**: Rejection with `PERMISSION_ERROR`
- **Rationale**: Prevents accidental cross-tenant execution

**Test 2: Cross-Tenant Resource Rejection**
- **Setup**: Resource R owned by Tenant A, execution context = Tenant B
- **Input**: Workflow references Resource R
- **Expected**: Rejection during Context Resolution
- **Error**: `PERMISSION_ERROR`

**Test 3: Global Resource Access**
- **Setup**: Resource G marked as global (`is_global = true`)
- **Input**: Workflow executed by Tenant A references Resource G
- **Expected**: Access granted (global resource accessible)

**Test 4: Runtime Escalation Attempt**
- **Setup**: Workflow validated with Resource R1 (Tenant A)
- **Input**: During execution, code attempts to access Resource R2 (Tenant B)
- **Expected**: Immediate failure with `PERMISSION_ERROR`
- **Rationale**: Runtime checks prevent escalation

**Test 5: Side Effect Isolation**
- **Setup**: Workflow executed by Tenant A contains write operation
- **Input**: Execute workflow
- **Expected**: Writes ONLY to Tenant A scope
- **Verification**: Tenant B storage remains unchanged

Full test specifications available in `compliance-tests/NORP-002-tests.md`.

---

## 10. Rationale Summary

**Core Principle**: Tenant isolation is a non-negotiable invariant.

Any ambiguity in tenant resolution or resource scoping invalidates system trust and regulatory compliance.

This principle applies regardless of orchestration complexity, programming language, or infrastructure.

---

## 11. Future Extensions

Future NORP specifications MAY define:
- Hierarchical tenant models with inheritance
- Cross-tenant resource sharing contracts
- Zero-trust execution contexts
- Auditable isolation policies
- Tenant migration and portability

---

## 12. References

- [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119): Key words for use in RFCs to Indicate Requirement Levels
- Multi-Tenant SaaS Security Best Practices
- Zero Trust Architecture Principles (NIST SP 800-207)
- OWASP Multi-Tenancy Cheat Sheet

---

## 13. Acknowledgments

This specification is derived from production multi-tenant isolation patterns at NeuraScope.

The authors thank security reviewers and early adopters for their feedback.

---

## Appendix A: Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | 2026-01-09 | Status upgraded to Stable. Added conflict resolution algorithm, isolation verification test, cross-tenant collaboration rules, anti-patterns, code review checklist. |
| 1.1 | 2026-01-08 | Added tenant resolution algorithm, scoping mechanisms, global resource criteria. |
| 1.0 | 2026-01-07 | Initial draft. |

---

## Citation

```bibtex
@techreport{norp002-2026,
  title={{NORP-002: Multi-Tenant Resource Isolation for AI Orchestration Systems}},
  author={{NORP Working Group}},
  institution={NeuraScope},
  year={2026},
  month={January},
  day={9},
  version={1.2},
  status={Stable},
  url={https://norp.neurascope.ai/specs/NORP-002},
  license={CC BY 4.0}
}
```

---

**NORP-002 v1.2 STABLE**
**NeuraScope Orchestration Reference Patterns**
**© 2026 NeuraScope CONVERWAY - Licensed under CC BY 4.0**
