<?php

namespace NORP\PHP;

use NORP\PHP\DTOs\ValidationResult;

/**
 * BlueprintValidator - NORP-001 and NORP-004 Reference Implementation
 *
 * Implements:
 * - NORP-001: Pre-Execution Validation Pipeline (Structural Validation stage)
 * - NORP-004: Cycle Detection (DFS algorithm O(V+E))
 * - NORP-007: Cost Estimation
 *
 * @license MIT
 * @copyright 2026 NeuraScope CONVERWAY
 */
class BlueprintValidator
{
    /**
     * Validate complete workflow
     *
     * @param array $workflow - Workflow definition with 'nodes' array
     * @param callable|null $resourceValidator - Optional callback to validate resource existence
     * @return ValidationResult
     */
    public function validate(array $workflow, ?callable $resourceValidator = null): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // 1. Structural validation
        if (empty($workflow['nodes'])) {
            $errors[] = 'At least one node required in workflow';
            return new ValidationResult(false, $errors, $warnings);
        }

        // 2. Cycle detection (NORP-004)
        if ($this->detectCycles($workflow['nodes'])) {
            $errors[] = 'Cycle detected in execution graph';
        }

        // 3. Validate node dependencies
        foreach ($workflow['nodes'] as $node) {
            $nodeId = $node['id'] ?? 'unknown';

            if (!empty($node['depends_on'])) {
                foreach ($node['depends_on'] as $depId) {
                    if (!$this->nodeExists($workflow['nodes'], $depId)) {
                        $errors[] = "Node '{$nodeId}' depends on non-existent node '{$depId}'";
                    }
                }
            }
        }

        // 4. Validate resources (if validator provided)
        if ($resourceValidator !== null) {
            foreach ($workflow['nodes'] as $node) {
                $resourceErrors = $resourceValidator($node);
                $errors = array_merge($errors, $resourceErrors);
            }
        }

        // 5. Estimate cost (NORP-007)
        $estimatedCost = $this->estimateCost($workflow['nodes']);

        if ($estimatedCost > 100) {
            $warnings[] = "High estimated cost: \${$estimatedCost} (based on 1K executions/month)";
        }

        return new ValidationResult(
            valid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            estimated_cost: $estimatedCost
        );
    }

    /**
     * Detect cycles in graph using DFS (NORP-004)
     *
     * Complexity: O(V + E)
     *
     * @param array $nodes
     * @return bool - True if cycle detected
     */
    private function detectCycles(array $nodes): bool
    {
        $graph = $this->buildGraph($nodes);
        $visited = [];
        $recStack = [];

        foreach (array_keys($graph) as $nodeId) {
            if ($this->isCyclicUtil($nodeId, $graph, $visited, $recStack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * DFS recursive cycle detection
     *
     * @param string $nodeId
     * @param array $graph
     * @param array $visited - Fully explored nodes
     * @param array $recStack - Recursion stack (detects back-edge)
     * @return bool
     */
    private function isCyclicUtil(string $nodeId, array $graph, array &$visited, array &$recStack): bool
    {
        // Back-edge detected → CYCLE
        if (isset($recStack[$nodeId])) {
            return true;
        }

        // Already fully explored
        if (isset($visited[$nodeId])) {
            return false;
        }

        // Mark as visiting
        $visited[$nodeId] = true;
        $recStack[$nodeId] = true;

        // Explore neighbors
        foreach ($graph[$nodeId] ?? [] as $neighbor) {
            if ($this->isCyclicUtil($neighbor, $graph, $visited, $recStack)) {
                return true;
            }
        }

        // Backtrack (remove from recursion stack)
        unset($recStack[$nodeId]);

        return false;
    }

    /**
     * Build dependency graph
     *
     * @param array $nodes
     * @return array - ['node_id' => ['dependent_node_1', 'dependent_node_2']]
     */
    private function buildGraph(array $nodes): array
    {
        $graph = [];

        // Initialize all nodes
        foreach ($nodes as $node) {
            $nodeId = $node['id'] ?? uniqid('node_');
            $graph[$nodeId] = [];
        }

        // Add edges (inverted for DFS)
        foreach ($nodes as $node) {
            $nodeId = $node['id'] ?? uniqid('node_');
            $dependencies = $node['depends_on'] ?? [];

            foreach ($dependencies as $depId) {
                if (!isset($graph[$depId])) {
                    $graph[$depId] = [];
                }
                $graph[$depId][] = $nodeId;
            }
        }

        return $graph;
    }

    /**
     * Check if node exists
     *
     * @param array $nodes
     * @param string $nodeId
     * @return bool
     */
    private function nodeExists(array $nodes, string $nodeId): bool
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Estimate workflow cost (NORP-007)
     *
     * @param array $nodes
     * @return float - Estimated cost in USD
     */
    private function estimateCost(array $nodes): float
    {
        $totalCost = 0;
        $executionsPerMonth = 1000;

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'llm_call') {
                $maxTokens = $node['config']['max_tokens'] ?? 1000;
                $model = $node['config']['model'] ?? 'gpt-3.5-turbo';

                $pricing = $this->getModelPricing($model);

                // Estimate input tokens (average prompt)
                $inputTokens = 500;

                $costPerExecution =
                    ($inputTokens / 1000 * $pricing['input']) +
                    ($maxTokens / 1000 * $pricing['output']);

                $totalCost += $costPerExecution * $executionsPerMonth;
            }
        }

        return round($totalCost, 2);
    }

    /**
     * Get model pricing (NORP-007)
     *
     * @param string $model
     * @return array - ['input' => float, 'output' => float] ($/1K tokens)
     */
    private function getModelPricing(string $model): array
    {
        $pricingMap = [
            // Anthropic
            'claude-3-5-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],

            // OpenAI
            'gpt-4-turbo' => ['input' => 0.010, 'output' => 0.030],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],

            // Mistral
            'mistral-large' => ['input' => 0.004, 'output' => 0.012],

            // Local (free)
            'llama' => ['input' => 0.000, 'output' => 0.000],
        ];

        // Partial match (e.g., "mistral-7b" matches "mistral")
        foreach ($pricingMap as $modelKey => $pricing) {
            if (str_contains(strtolower($model), strtolower($modelKey))) {
                return $pricing;
            }
        }

        // Default: average pricing
        return ['input' => 0.010, 'output' => 0.030];
    }
}
