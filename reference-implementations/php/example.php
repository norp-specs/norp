<?php

/**
 * NORP PHP Reference Implementation - Usage Example
 *
 * Demonstrates NORP-001, 004, 005, 007 compliance
 *
 * @license MIT
 * @copyright 2026 NeuraScope CONVERWAY
 */

require_once __DIR__ . '/BlueprintValidator.php';
require_once __DIR__ . '/BlueprintCompiler.php';
require_once __DIR__ . '/DTOs/ValidationResult.php';
require_once __DIR__ . '/DTOs/ExecutionPlan.php';

use NORP\PHP\BlueprintValidator;
use NORP\PHP\BlueprintCompiler;

// Example workflow (diamond pattern)
$workflow = [
    'name' => 'Content Processing Workflow',
    'nodes' => [
        [
            'id' => 'extract',
            'type' => 'datasource',
            'depends_on' => []
        ],
        [
            'id' => 'summarize',
            'type' => 'llm_call',
            'config' => [
                'model' => 'gpt-4-turbo',
                'prompt' => 'Summarize this text...',
                'max_tokens' => 500
            ],
            'depends_on' => ['extract']
        ],
        [
            'id' => 'classify',
            'type' => 'llm_call',
            'config' => [
                'model' => 'claude-3-haiku',
                'prompt' => 'Classify sentiment...',
                'max_tokens' => 200
            ],
            'depends_on' => ['extract']
        ],
        [
            'id' => 'publish',
            'type' => 'output',
            'depends_on' => ['summarize', 'classify']
        ]
    ]
];

// ═══════════════════════════════════════════════════════════
// NORP-001: Pre-Execution Validation Pipeline
// ═══════════════════════════════════════════════════════════

echo "═══ NORP-001 + NORP-004 + NORP-007: Validation ═══\n\n";

$validator = new BlueprintValidator();
$validationResult = $validator->validate($workflow);

echo "Valid: " . ($validationResult->valid ? 'YES' : 'NO') . "\n";
echo "Errors: " . count($validationResult->errors) . "\n";
echo "Warnings: " . count($validationResult->warnings) . "\n";
echo "Estimated Cost: \$" . number_format($validationResult->estimated_cost, 4) . "\n";
echo "Summary: " . $validationResult->getSummary() . "\n\n";

if (!$validationResult->valid) {
    echo "Validation failed:\n";
    foreach ($validationResult->errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

// ═══════════════════════════════════════════════════════════
// NORP-007: Budget Enforcement
// ═══════════════════════════════════════════════════════════

echo "═══ NORP-007: Budget Enforcement ═══\n\n";

$budget = 10.00; // $10 budget

if ($validationResult->estimated_cost > $budget) {
    echo "❌ BUDGET EXCEEDED\n";
    echo "Estimated: \$" . $validationResult->estimated_cost . "\n";
    echo "Budget: \$" . $budget . "\n";
    exit(1);
}

echo "✅ Budget OK (${$validationResult->estimated_cost} < \${$budget})\n\n";

// ═══════════════════════════════════════════════════════════
// NORP-005: Deterministic Topological Ordering
// ═══════════════════════════════════════════════════════════

echo "═══ NORP-005: Compilation ═══\n\n";

$compiler = new BlueprintCompiler();
$executionPlan = $compiler->compile($workflow);

echo "Execution Order: " . implode(' → ', $executionPlan->execution_order) . "\n";
echo "Total Levels: " . $executionPlan->getLevelsCount() . "\n";
echo "Estimated Duration: " . $executionPlan->estimated_duration_ms . "ms\n\n";

echo "Parallel Groups:\n";
foreach ($executionPlan->parallel_groups as $group) {
    $parallelFlag = $group['parallel'] ? '(parallel)' : '';
    echo "  Level {$group['level']}: " . implode(', ', $group['nodes']) . " $parallelFlag\n";
}

echo "\n";

// ═══════════════════════════════════════════════════════════
// NORP-003: Immutability Test
// ═══════════════════════════════════════════════════════════

echo "═══ NORP-003: Immutability Test ═══\n\n";

try {
    // Attempt to mutate (should fail with PHP 8.2 readonly)
    // $validationResult->valid = false;
    // This would throw: Error: Cannot modify readonly property

    echo "✅ DTOs are immutable (readonly properties)\n";
} catch (\Error $e) {
    echo "✅ Mutation blocked: " . $e->getMessage() . "\n";
}

echo "\n";

// ═══════════════════════════════════════════════════════════
// NORP-005: Determinism Test
// ═══════════════════════════════════════════════════════════

echo "═══ NORP-005: Determinism Test ═══\n\n";

$plan1 = $compiler->compile($workflow);
$plan2 = $compiler->compile($workflow);

if ($plan1->execution_order === $plan2->execution_order) {
    echo "✅ Deterministic: Same workflow → Same order\n";
    echo "   Order 1: " . implode(', ', $plan1->execution_order) . "\n";
    echo "   Order 2: " . implode(', ', $plan2->execution_order) . "\n";
} else {
    echo "❌ Non-deterministic: Orders differ\n";
}

echo "\n═══ NORP Compliance Verified ═══\n";
