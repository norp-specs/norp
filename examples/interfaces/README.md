# NORP Interface Examples

This directory contains example `norp-interface.json` files demonstrating NORP-008 compliance.

---

## Files

### neurascope-full-compliance.json

**Orchestrator**: NeuraScope Blueprint Engine (production SaaS, multi-tenant)

**NORP Compliance**: All 7 specs (NORP-001 through NORP-007)

**Use case**: Enterprise multi-tenant AI orchestration platform with full governance (validation, isolation, cost control)

**Key features**:
- Multi-tenant isolation (organization-level)
- Deterministic ordering (Kahn's algorithm, lexicographic tie-breaking)
- Cost estimation with 30% conservative margin
- Resource pooling with execution-scoped isolation

---

### partial-compliance.json

**Orchestrator**: LocalFlow Engine (single-tenant, local models)

**NORP Compliance**: 5 specs (NORP-001, 003, 004, 005) - Partial compliance with transparent rationale

**Use case**: On-premise single-tenant deployment using local LLMs (Ollama/MLX)

**Key features**:
- NORP-002 not applicable (single-tenant)
- NORP-006 not applicable (stateless execution)
- NORP-007 not applicable (local models, zero API cost)
- Honest declaration of limitations

---

### Template

See `schemas/norp-interface.template.json` for a starter template with all fields.

---

## How to Use

### Step 1: Choose Template

Start with `norp-interface.template.json` and fill in your orchestrator details.

### Step 2: Declare Compliance

For each NORP spec, set `compliance.NORP-XXX` to `true` or `false`.

### Step 3: Add Details

If `compliant: true`, add the corresponding `norp_XXX_*` section with required fields.

If `compliant: false`, add explanation to `non_compliance_rationale`.

### Step 4: Validate

```bash
# Using ajv-cli
ajv validate -s ../schemas/norp-interface.schema.json -d your-interface.json

# Using norp-validator (future tool)
norp-validator --check your-interface.json
```

### Step 5: Expose

Expose via:
- HTTP endpoint: `https://your-orchestrator.com/norp-interface.json`
- Repository file: `.norp-interface.json` in repo root
- CLI: `your-orch --norp-interface`

---

## Validation Checklist

Before publishing your interface:

- [ ] JSON is well-formed (no syntax errors)
- [ ] Validates against `norp-interface.schema.json`
- [ ] All required fields present
- [ ] Compliance declarations match actual implementation
- [ ] Rationales provided for `false` declarations
- [ ] Versioning correct (`norp_version` = highest NORP spec implemented)

---

**NORP Interface Examples v1.0**
**© 2026 NeuraScope CONVERWAY**
