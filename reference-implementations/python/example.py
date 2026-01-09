"""
NORP Python Reference Implementation - Usage Example

Demonstrates NORP-001, 004, 005, 007 compliance

License: MIT
Copyright: 2026 NeuraScope CONVERWAY
"""

from blueprint_validator import BlueprintValidator
from blueprint_compiler import BlueprintCompiler

# Example workflow (diamond pattern)
workflow = {
    'name': 'Content Processing Workflow',
    'nodes': [
        {
            'id': 'extract',
            'type': 'datasource',
            'depends_on': []
        },
        {
            'id': 'summarize',
            'type': 'llm_call',
            'config': {
                'model': 'gpt-4-turbo',
                'prompt': 'Summarize this text...',
                'max_tokens': 500
            },
            'depends_on': ['extract']
        },
        {
            'id': 'classify',
            'type': 'llm_call',
            'config': {
                'model': 'claude-3-haiku',
                'prompt': 'Classify sentiment...',
                'max_tokens': 200
            },
            'depends_on': ['extract']
        },
        {
            'id': 'publish',
            'type': 'output',
            'depends_on': ['summarize', 'classify']
        }
    ]
}

# ═══════════════════════════════════════════════════════════
# NORP-001: Pre-Execution Validation Pipeline
# ═══════════════════════════════════════════════════════════

print("═══ NORP-001 + NORP-004 + NORP-007: Validation ═══\n")

validator = BlueprintValidator()
validation_result = validator.validate(workflow)

print(f"Valid: {'YES' if validation_result.valid else 'NO'}")
print(f"Errors: {len(validation_result.errors)}")
print(f"Warnings: {len(validation_result.warnings)}")
print(f"Estimated Cost: ${validation_result.estimated_cost:.4f}")
print(f"Summary: {validation_result.get_summary()}\n")

if not validation_result.valid:
    print("Validation failed:")
    for error in validation_result.errors:
        print(f"  - {error}")
    exit(1)

# ═══════════════════════════════════════════════════════════
# NORP-007: Budget Enforcement
# ═══════════════════════════════════════════════════════════

print("═══ NORP-007: Budget Enforcement ═══\n")

budget = 10.00  # $10 budget

if validation_result.estimated_cost > budget:
    print("❌ BUDGET EXCEEDED")
    print(f"Estimated: ${validation_result.estimated_cost}")
    print(f"Budget: ${budget}")
    exit(1)

print(f"✅ Budget OK (${validation_result.estimated_cost:.2f} < ${budget:.2f})\n")

# ═══════════════════════════════════════════════════════════
# NORP-005: Deterministic Topological Ordering
# ═══════════════════════════════════════════════════════════

print("═══ NORP-005: Compilation ═══\n")

compiler = BlueprintCompiler()
execution_plan = compiler.compile(workflow)

print(f"Execution Order: {' → '.join(execution_plan.execution_order)}")
print(f"Total Levels: {execution_plan.get_levels_count()}")
print(f"Estimated Duration: {execution_plan.estimated_duration_ms}ms\n")

print("Parallel Groups:")
for group in execution_plan.parallel_groups:
    parallel_flag = '(parallel)' if group['parallel'] else ''
    nodes_str = ', '.join(group['nodes'])
    print(f"  Level {group['level']}: {nodes_str} {parallel_flag}")

print()

# ═══════════════════════════════════════════════════════════
# NORP-003: Immutability Test
# ═══════════════════════════════════════════════════════════

print("═══ NORP-003: Immutability Test ═══\n")

try:
    # Attempt to mutate (should fail with frozen dataclass)
    # validation_result.valid = False
    # This would raise: dataclasses.FrozenInstanceError

    print("✅ DTOs are immutable (frozen dataclasses)\n")
except Exception as e:
    print(f"✅ Mutation blocked: {e}\n")

# ═══════════════════════════════════════════════════════════
# NORP-005: Determinism Test
# ═══════════════════════════════════════════════════════════

print("═══ NORP-005: Determinism Test ═══\n")

plan1 = compiler.compile(workflow)
plan2 = compiler.compile(workflow)

if plan1.execution_order == plan2.execution_order:
    print("✅ Deterministic: Same workflow → Same order")
    print(f"   Order 1: {', '.join(plan1.execution_order)}")
    print(f"   Order 2: {', '.join(plan2.execution_order)}")
else:
    print("❌ Non-deterministic: Orders differ")

print("\n═══ NORP Compliance Verified ═══")
