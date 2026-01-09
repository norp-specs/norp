<?php

namespace NORP\PHP;

use NORP\PHP\DTOs\ExecutionPlan;

/**
 * BlueprintCompiler - NORP-005 Reference Implementation
 *
 * Implements:
 * - NORP-005: Deterministic Topological Ordering (Kahn's algorithm)
 * - NORP-004: Cycle detection via topological sort
 *
 * @license MIT
 * @copyright 2026 NeuraScope CONVERWAY
 */
class BlueprintCompiler
{
    /**
     * Compile workflow into deterministic execution plan
     *
     * @param array $workflow - Workflow with 'nodes' array
     * @return ExecutionPlan
     * @throws \Exception if cycle detected
     */
    public function compile(array $workflow): ExecutionPlan
    {
        $nodes = $workflow['nodes'] ?? [];

        if (empty($nodes)) {
            throw new \Exception('No nodes to compile');
        }

        // 1. Build dependency graph
        $graph = $this->buildDependencyGraph($nodes);

        // 2. Topological sort (NORP-005)
        $executionOrder = $this->topologicalSort($nodes, $graph);

        // 3. Detect parallelizable groups
        $parallelGroups = $this->detectParallelGroups($graph);

        // 4. Estimate duration
        $estimatedDuration = $this->estimateDuration($nodes);

        return new ExecutionPlan(
            nodes: $nodes,
            execution_order: $executionOrder,
            parallel_groups: $parallelGroups,
            estimated_duration_ms: $estimatedDuration
        );
    }

    /**
     * Topological sort using Kahn's algorithm (NORP-005)
     *
     * Complexity: O(V + E)
     *
     * @param array $nodes
     * @param array $graph
     * @return array - Deterministic execution order (node IDs)
     * @throws \Exception if cycle detected
     */
    private function topologicalSort(array $nodes, array $graph): array
    {
        // 1. Calculate in-degree for each node
        $inDegree = [];

        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            $inDegree[$nodeId] = 0;
        }

        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            $dependencies = $node['depends_on'] ?? [];

            foreach ($dependencies as $depId) {
                if (isset($inDegree[$depId])) {
                    $inDegree[$nodeId]++;
                }
            }
        }

        // 2. Queue with zero in-degree nodes (NORP-005: deterministic tie-breaking)
        $queue = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $queue[] = $nodeId;
            }
        }

        // NORP-005: Deterministic tie-breaking (lexicographic sort)
        sort($queue);

        // 3. BFS processing
        $result = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $result[] = $current;

            $newlyEligible = [];

            // Find nodes that depend on current
            foreach ($nodes as $node) {
                $nodeId = $node['id'];

                if (in_array($current, $node['depends_on'] ?? [])) {
                    $inDegree[$nodeId]--;

                    if ($inDegree[$nodeId] === 0) {
                        $newlyEligible[] = $nodeId;
                    }
                }
            }

            // NORP-005: Deterministic reinsertion (sorted)
            if (!empty($newlyEligible)) {
                $queue = array_merge($queue, $newlyEligible);
                sort($queue);
            }
        }

        // 4. Verify all nodes sorted (else cycle exists)
        if (count($result) !== count($nodes)) {
            throw new \Exception(
                'Compilation failed: Cycle detected in graph. ' .
                'Nodes sorted: ' . count($result) . '/' . count($nodes)
            );
        }

        return $result;
    }

    /**
     * Build dependency graph
     *
     * @param array $nodes
     * @return array - ['node_id' => ['dep1', 'dep2']]
     */
    private function buildDependencyGraph(array $nodes): array
    {
        $graph = [];

        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            $graph[$nodeId] = $node['depends_on'] ?? [];
        }

        return $graph;
    }

    /**
     * Detect parallelizable groups (NORP-005)
     *
     * Nodes at same dependency level can execute in parallel
     *
     * @param array $graph
     * @return array
     */
    private function detectParallelGroups(array $graph): array
    {
        $levels = $this->computeLevels($graph);
        $groups = [];

        foreach ($levels as $level => $nodeIds) {
            $groups[] = [
                'level' => $level,
                'nodes' => $nodeIds,
                'parallel' => count($nodeIds) > 1,
            ];
        }

        return $groups;
    }

    /**
     * Compute dependency levels (BFS)
     *
     * @param array $graph
     * @return array - ['level' => ['node1', 'node2']]
     */
    private function computeLevels(array $graph): array
    {
        $levels = [];
        $nodeLevel = [];
        $queue = [];

        // Init: Nodes without dependencies = level 0
        foreach ($graph as $nodeId => $dependencies) {
            if (empty($dependencies)) {
                $queue[] = [$nodeId, 0];
                $nodeLevel[$nodeId] = 0;
            }
        }

        // BFS
        while (!empty($queue)) {
            [$current, $level] = array_shift($queue);

            if (!isset($levels[$level])) {
                $levels[$level] = [];
            }
            $levels[$level][] = $current;

            // Find nodes that depend on current
            foreach ($graph as $nodeId => $dependencies) {
                if (in_array($current, $dependencies) && !isset($nodeLevel[$nodeId])) {
                    // Calculate level = max(dependencies levels) + 1
                    $maxDepLevel = 0;
                    foreach ($dependencies as $depId) {
                        if (isset($nodeLevel[$depId])) {
                            $maxDepLevel = max($maxDepLevel, $nodeLevel[$depId]);
                        }
                    }

                    $nodeLevel[$nodeId] = $maxDepLevel + 1;
                    $queue[] = [$nodeId, $maxDepLevel + 1];
                }
            }
        }

        return $levels;
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
     * @return float - Cost in USD
     */
    private function estimateCost(array $nodes): float
    {
        $totalCost = 0;

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'llm_call') {
                $maxTokens = $node['config']['max_tokens'] ?? 1000;
                $model = $node['config']['model'] ?? 'gpt-3.5-turbo';

                $pricing = $this->getModelPricing($model);

                // NORP-007: Token estimation (chars / 4 for English)
                $prompt = $node['config']['prompt'] ?? '';
                $inputTokens = strlen($prompt) / 4;

                // NORP-007: Cost formula
                $costPerExecution =
                    ($inputTokens / 1000 * $pricing['input']) +
                    ($maxTokens / 1000 * $pricing['output']);

                $totalCost += $costPerExecution;
            }
        }

        // NORP-007: Conservative estimation (30% margin)
        return round($totalCost * 1.3, 4);
    }

    /**
     * Get model pricing (NORP-007 Appendix B)
     *
     * @param string $model
     * @return array - ['input' => float, 'output' => float]
     */
    private function getModelPricing(string $model): array
    {
        $pricingMap = [
            'claude-3-5-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
            'gpt-4-turbo' => ['input' => 0.010, 'output' => 0.030],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            'mistral-large' => ['input' => 0.004, 'output' => 0.012],
            'llama' => ['input' => 0.000, 'output' => 0.000],
        ];

        foreach ($pricingMap as $modelKey => $pricing) {
            if (str_contains(strtolower($model), strtolower($modelKey))) {
                return $pricing;
            }
        }

        return ['input' => 0.010, 'output' => 0.030];
    }
}
