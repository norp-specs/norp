<?php

namespace NORP\PHP\DTOs;

/**
 * ExecutionPlan - Immutable DTO for compiled execution plan
 *
 * NORP Compliance:
 * - NORP-003: Immutable DTO (readonly properties)
 * - NORP-005: Deterministic execution order + parallel groups
 *
 * @license MIT
 * @copyright 2026 NeuraScope CONVERWAY
 */
readonly class ExecutionPlan
{
    public function __construct(
        public array $nodes,
        public array $execution_order,
        public array $parallel_groups,
        public int $estimated_duration_ms,
    ) {}

    /**
     * Get nodes at specific dependency level
     *
     * @param int $level - DAG level (0 = source nodes)
     * @return array - Node IDs at this level
     */
    public function getLevel(int $level): array
    {
        return $this->parallel_groups[$level]['nodes'] ?? [];
    }

    /**
     * Check if level can be parallelized
     *
     * @param int $level
     * @return bool
     */
    public function isParallelizable(int $level): bool
    {
        return ($this->parallel_groups[$level]['parallel'] ?? false) &&
               count($this->parallel_groups[$level]['nodes'] ?? []) > 1;
    }

    /**
     * Get total number of levels in DAG
     *
     * @return int
     */
    public function getLevelsCount(): int
    {
        return count($this->parallel_groups);
    }

    /**
     * Get execution plan statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $parallelizableCount = 0;

        foreach ($this->parallel_groups as $group) {
            if ($group['parallel'] ?? false) {
                $parallelizableCount += count($group['nodes']);
            }
        }

        return [
            'total_nodes' => count($this->nodes),
            'levels' => $this->getLevelsCount(),
            'parallelizable_nodes' => $parallelizableCount,
            'estimated_duration_ms' => $this->estimated_duration_ms,
        ];
    }
}
