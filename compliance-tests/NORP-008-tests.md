# NORP-008 Compliance Test Suite

## Overview

This document defines the **mandatory compliance tests** for NORP-008 (NORP Interface Specification).

An orchestrator is **NORP-008 compliant** if its exposed interface passes **all mandatory tests** in this suite.

**Important distinction**:
- **NORP-008 tests** validate the **interface itself** (JSON structure, schema conformance)
- **NORP-001 to 007 tests** validate the **orchestrator behavior** (does it actually do what it declares?)

An orchestrator can be NORP-008 compliant (valid interface) while declaring `compliant: false` for all NORP specs.

---

## Test Environment Setup

### Prerequisites
- Access to orchestrator's NORP Interface (HTTP endpoint, file, or CLI)
- JSON Schema validator (e.g., `ajv`, Python `jsonschema`, Go `gojsonschema`)
- NORP Interface Schema: `schemas/norp-interface.schema.json`

---

## Mandatory Tests

### Test 1: JSON Schema Validity

**Objective**: Verify that the exposed interface is valid JSON and conforms to NORP Interface Schema.

**Actions**:
1. Fetch interface (e.g., `curl https://orchestrator.example.com/norp-interface.json`)
2. Parse as JSON
3. Validate against `schemas/norp-interface.schema.json`

**Expected Behavior**:
- JSON is well-formed (no syntax errors)
- JSON validates against schema
- All required root properties present: `norp_version`, `norp_interface_version`, `orchestrator`, `compliance`

**Pass Criteria**:
- ✅ Valid JSON syntax
- ✅ Schema validation passes
- ✅ No missing required fields

**Example validation** (using `ajv-cli`):
```bash
curl https://orch.example.com/norp-interface.json > interface.json
ajv validate -s schemas/norp-interface.schema.json -d interface.json
→ interface.json valid
```

---

### Test 2: Required Fields for Declared Compliance

**Objective**: Verify that when `compliance.NORP-XXX == true`, the corresponding detailed section is present with required fields.

**Setup**: Interface declares `"NORP-001": true`

**Actions**:
1. Parse interface JSON
2. Check `compliance.NORP-001` value
3. If `true`, verify `norp_001_validation_pipeline` section exists
4. Verify required fields for that section (`stages`, `stage_order`, `fail_fast`)

**Expected Behavior**:
- If `compliance.NORP-001 == true`, then `norp_001_validation_pipeline` section MUST exist
- Required fields for that section MUST be present
- Repeat for all 7 NORP specs

**Pass Criteria**:
- ✅ For each `compliance.NORP-XXX == true`, corresponding section exists
- ✅ All required fields present in each section

**Example check**:
```javascript
const interface = JSON.parse(interfaceJSON);

for (const [norp, isCompliant] of Object.entries(interface.compliance)) {
  if (isCompliant) {
    const sectionName = `norp_${norp.toLowerCase().replace('-', '_')}`;
    assert(interface[sectionName], `Section ${sectionName} missing but ${norp} declared compliant`);
  }
}
```

---

### Test 3: Non-Compliance Rationale for False Declarations

**Objective**: Verify that when `compliance.NORP-XXX == false`, a rationale is provided (SHOULD requirement).

**Setup**: Interface declares `"NORP-002": false`

**Actions**:
1. Check if `non_compliance_rationale.NORP-002` exists
2. Verify it contains explanation (non-empty string)

**Expected Behavior** (SHOULD, not MUST):
- If `compliance.NORP-002 == false`, `non_compliance_rationale.NORP-002` SHOULD exist
- Rationale SHOULD be meaningful (e.g., "Single-tenant system", not just "N/A")

**Pass Criteria**:
- ✅ Rationale field exists
- ✅ Rationale is non-empty string
- ⚠️ Warning (not failure) if missing

**Rationale**: Transparency is encouraged but not enforced. An orchestrator can declare `false` without explanation (less friendly, but valid).

---

### Test 4: Versioning Consistency

**Objective**: Verify that `norp_version` is consistent with highest NORP spec version declared.

**Setup**: Interface declares compliance with NORP-001 v1.2 and NORP-007 v1.0

**Actions**:
1. Parse `norp_version` field
2. Determine highest version from compliance declarations (if available in per-NORP sections)

**Expected Behavior**:
- `norp_version` SHOULD equal highest NORP spec version implemented
- Example: If NORP-001 v1.2, declare `"norp_version": "1.2"`

**Pass Criteria**:
- ✅ `norp_version` format valid (e.g., "1.2", not "v1.2" or "1.2.0")
- ⚠️ Warning if `norp_version` seems inconsistent (e.g., "1.0" when NORP-001 v1.2 declared)

**Note**: This is a SHOULD requirement. Version mismatch is a warning, not failure.

---

### Test 5: Partial Compliance Declaration Validity

**Objective**: Verify that partial compliance is explicitly declared (not all true, not all false).

**Setup**: Orchestrator is single-tenant (NORP-002 not applicable)

**Actions**:
1. Check if `compliance.NORP-002 == false`
2. Verify `non_compliance_rationale.NORP-002` contains "not applicable" or similar

**Expected Behavior**:
- Partial compliance is valid (not all specs must be true)
- Rationale clearly indicates "not applicable" vs "not implemented"

**Pass Criteria**:
- ✅ At least one `compliance.NORP-XXX == false` (proves honesty, not claiming universal compliance)
- ✅ Rationale distinguishes "not applicable" from "not implemented"

**Example valid rationales**:
- ✅ "Single-tenant system, multi-tenant isolation not applicable"
- ✅ "All models local (MLX), cost estimation not applicable"
- ⚠️ "Not implemented yet" (valid but suggests future work)
- ❌ "" (empty rationale - warning)

---

## Optional Tests (Recommended)

### Test 6: Cross-Validation with Behavioral Tests

**Objective**: Verify that interface declarations match actual orchestrator behavior.

**Setup**:
- Interface declares `"NORP-005": true`
- Interface declares `"tie_breaking": "lexicographic"`

**Actions**:
1. Submit same workflow to orchestrator twice
2. Compare execution orders
3. Verify they are identical (NORP-005 behavior)
4. Verify tie-breaking is indeed lexicographic (if nodes A, M, Z → order is [A, M, Z])

**Expected Behavior**:
- Declared capability matches actual behavior

**Pass Criteria**:
- ✅ Interface declaration == observed behavior

**Note**: This is **cross-validation** between NORP-008 (interface) and NORP-005 (behavior). It proves the interface is truthful.

**Limitation**: Requires access to orchestrator execution, not just interface. May not be feasible for all auditors.

---

### Test 7: Interface Accessibility

**Objective**: Verify that interface is accessible via declared method.

**Setup**: Orchestrator documentation claims interface at `https://orch.example.com/norp-interface.json`

**Actions**:
1. HTTP GET to URL
2. Verify response is JSON (Content-Type: application/json)
3. Verify response conforms to schema

**Expected Behavior**:
- Interface is accessible without authentication (public discovery)
- OR authentication method is documented

**Pass Criteria**:
- ✅ HTTP 200 response
- ✅ Valid JSON returned
- ✅ Schema conformant

---

## Compliance Report Template

```markdown
# NORP-008 Compliance Report

**Orchestrator**: [Name]
**Version**: [Version]
**Interface URL**: [URL or file path]
**Date**: [Test Date]

## Test Results

| Test | Status | Notes |
|------|--------|-------|
| Test 1: Schema Validity | ✅ Pass | Valid JSON, schema conformant |
| Test 2: Required Fields | ✅ Pass | All sections present for declared compliance |
| Test 3: Rationale for False | ⚠️ Warning | NORP-002 false but no rationale |
| Test 4: Versioning | ✅ Pass | norp_version=1.2 consistent |
| Test 5: Partial Compliance | ✅ Pass | NORP-002, 007 declared false (transparent) |

## Compliance Status

✅ **NORP-008 COMPLIANT**

Interface is valid and exposes capabilities in standardized format.

## Declared NORP Compliance

- NORP-001: ✅ Compliant
- NORP-002: ❌ Not applicable (single-tenant)
- NORP-003: ✅ Compliant
- NORP-004: ✅ Compliant
- NORP-005: ✅ Compliant
- NORP-006: ❌ Not applicable (stateless)
- NORP-007: ❌ Not applicable (local models)

## Recommendations

- Consider adding rationale for NORP-002 false declaration
- Validate behavioral compliance via NORP-001, 003, 004, 005 test suites
```

---

## Edge Cases

### Case 1: Empty Compliance (All False)

**Scenario**: Orchestrator declares all 7 NORP specs as `false`

**Is this NORP-008 compliant?**
- ✅ **YES**, if interface is schema-valid and rationales provided
- This indicates "NORP-aware but non-compliant orchestrator"

**Value**: Transparency. Better to declare non-compliance than hide it.

---

### Case 2: Missing Interface

**Scenario**: Orchestrator has no `/norp-interface.json` endpoint

**Is this NORP-008 compliant?**
- ❌ **NO**. NORP-008 requires exposing interface.

**Action**: Cannot be certified NORP-008 compliant.

---

### Case 3: Invalid JSON

**Scenario**: Endpoint returns malformed JSON (syntax error)

**Is this NORP-008 compliant?**
- ❌ **NO**. Test 1 fails immediately.

---

### Case 4: Schema Valid but Behavioral Mismatch

**Scenario**:
- Interface declares `"NORP-005": true`, `"tie_breaking": "lexicographic"`
- Actual behavior: execution order is random

**Is this NORP-008 compliant?**
- ✅ **YES** (interface is schema-valid)
- ❌ **NO** for NORP-005 behavioral compliance

**Implication**: NORP-008 compliance ≠ truthfulness. Behavioral tests (NORP-005-tests.md) detect lying.

---

## Certification Process

To claim **NORP-008 Certification**:

1. **Expose interface** via HTTP endpoint, file, or CLI
2. **Pass all 5 mandatory tests** (Tests 1-5)
3. **Publish interface URL** in documentation
4. **Submit to NORP registry** (future: https://norp.neurascope.ai/registry)

**Optional but recommended**:
5. Pass cross-validation tests (Test 6) proving declarations are truthful
6. Pass NORP-001 to 007 behavioral tests for declared compliant specs

---

## Validation Commands

### Using ajv-cli (JSON Schema validator)

```bash
# Install ajv-cli
npm install -g ajv-cli

# Validate interface
ajv validate \
  -s schemas/norp-interface.schema.json \
  -d examples/interfaces/neurascope.json
→ examples/interfaces/neurascope.json valid
```

---

### Using Python jsonschema

```python
import json
import jsonschema

# Load schema
with open('schemas/norp-interface.schema.json') as f:
    schema = json.load(f)

# Load interface
with open('interface.json') as f:
    interface = json.load(f)

# Validate
jsonschema.validate(instance=interface, schema=schema)
print("✅ Valid NORP Interface")
```

---

### Using norp-validator (future tool)

```bash
norp-validator --check https://orch.example.com/norp-interface.json
→ ✅ Schema valid
→ ✅ Required fields present
→ ⚠️  NORP-002 declared false (rationale missing)
→ Overall: NORP-008 COMPLIANT
```

---

**NORP-008 Compliance Tests v1.0**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
