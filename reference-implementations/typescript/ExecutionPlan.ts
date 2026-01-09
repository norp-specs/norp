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

export interface Node {
  readonly id: string;
  readonly type: string;
  readonly depends_on?: ReadonlyArray<string>;
  readonly config?: any;
}

export interface ParallelGroup {
  readonly level: number;
  readonly nodes: ReadonlyArray<string>;
  readonly parallel: boolean;
}

export interface ExecutionPlan {
  readonly nodes: ReadonlyArray<Node>;
  readonly execution_order: ReadonlyArray<string>;
  readonly parallel_groups: ReadonlyArray<ParallelGroup>;
  readonly estimated_duration_ms: number;
}

export class ExecutionPlanImpl implements ExecutionPlan {
  readonly nodes: ReadonlyArray<Node>;
  readonly execution_order: ReadonlyArray<string>;
  readonly parallel_groups: ReadonlyArray<ParallelGroup>;
  readonly estimated_duration_ms: number;

  constructor(
    nodes: Node[],
    execution_order: string[],
    parallel_groups: ParallelGroup[],
    estimated_duration_ms: number
  ) {
    this.nodes = Object.freeze([...nodes]);
    this.execution_order = Object.freeze([...execution_order]);
    this.parallel_groups = Object.freeze([...parallel_groups]);
    this.estimated_duration_ms = estimated_duration_ms;

    // NORP-003: Deep immutability
    Object.freeze(this);
  }

  getLevel(level: number): ReadonlyArray<string> {
    return this.parallel_groups[level]?.nodes ?? [];
  }

  isParallelizable(level: number): boolean {
    const group = this.parallel_groups[level];
    return group?.parallel && group.nodes.length > 1;
  }

  getLevelsCount(): number {
    return this.parallel_groups.length;
  }

  getStats(): object {
    const parallelizableCount = this.parallel_groups
      .filter(g => g.parallel)
      .reduce((sum, g) => sum + g.nodes.length, 0);

    return {
      total_nodes: this.nodes.length,
      levels: this.getLevelsCount(),
      parallelizable_nodes: parallelizableCount,
      estimated_duration_ms: this.estimated_duration_ms
    };
  }
}
