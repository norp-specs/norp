# NORP Reference Implementations

This directory contains **reference implementations** of NORP specifications in multiple programming languages.

**License**: MIT (all reference code)

---

## Available Implementations

| Language | Status | NORP Coverage | Source |
|----------|--------|---------------|--------|
| **PHP** | ✅ Complete | NORP-001, 003, 004, 005, 007 | Extracted from NeuraScope production |
| **TypeScript** | 🔄 Planned | NORP-001 to 007 | Q2 2026 |
| **Python** | 🔄 Planned | NORP-001 to 007 | Q2 2026 |

---

## PHP Implementation

**Source**: Extracted from [NeuraScope](https://neurascope.ai) Blueprint Runtime Engine (production-tested on 10,000+ workflows).

**Files**:
- `php/BlueprintValidator.php` - NORP-001, NORP-004, NORP-007 (Validation, Cycle Detection, Cost Estimation)
- `php/BlueprintCompiler.php` - NORP-005 (Topological Sorting)
- `php/ContextManager.php` - NORP-006 (Resource Pooling)
- `php/DTOs/ValidationResult.php` - NORP-003 (Immutable DTOs)
- `php/DTOs/ExecutionPlan.php` - NORP-003
- `php/DTOs/ExecutionContext.php` - NORP-003

**Requirements**:
- PHP 8.2+ (for `readonly` properties)
- No framework dependencies (pure PHP)

---

## Usage

### PHP

```php
require_once 'reference-implementations/php/BlueprintValidator.php';
require_once 'reference-implementations/php/DTOs/ValidationResult.php';

use NORP\PHP\BlueprintValidator;

$validator = new BlueprintValidator();
$result = $validator->validate($workflow);

if (!$result->valid) {
    echo "Validation failed: " . implode(', ', $result->errors);
}
```

---

## Contributing

To contribute reference implementations:

1. Ensure **100% NORP compliance** (pass all tests from `compliance-tests/`)
2. Follow language-specific best practices
3. Include **unit tests** (>80% coverage)
4. Document **how to run tests**
5. License under **MIT**

See `governance/CONTRIBUTING.md` for full guidelines.

---

**NORP Reference Implementations**
**© 2026 NeuraScope CONVERWAY - Licensed under MIT**
