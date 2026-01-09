/**
 * NORP TypeScript Type Definitions
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

export interface ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;
  readonly warnings: ReadonlyArray<string>;
  readonly estimated_cost: number;
}

export interface ExecutionPlan {
  readonly nodes: ReadonlyArray<Node>;
  readonly execution_order: ReadonlyArray<string>;
  readonly parallel_groups: ReadonlyArray<ParallelGroup>;
  readonly estimated_duration_ms: number;
}
