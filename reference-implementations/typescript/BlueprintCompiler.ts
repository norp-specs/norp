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

import { ExecutionPlanImpl } from './ExecutionPlan';
import type { ExecutionPlan, Node, ParallelGroup } from './types';

export class BlueprintCompiler {
  /**
   * Compile workflow into deterministic execution plan
   */
  compile(workflow: { nodes: Node[] }): ExecutionPlan {
    const nodes = workflow.nodes || [];

    if (nodes.length === 0) {
      throw new Error('No nodes to compile');
    }

    // 1. Build dependency graph
    const graph = this.buildDependencyGraph(nodes);

    // 2. Topological sort (NORP-005)
    const executionOrder = this.topologicalSort(nodes, graph);

    // 3. Detect parallelizable groups
    const parallelGroups = this.detectParallelGroups(graph);

    // 4. Estimate duration
    const estimatedDuration = this.estimateDuration(nodes);

    return new ExecutionPlanImpl(
      nodes,
      executionOrder,
      parallelGroups,
      estimatedDuration
    );
  }

  /**
   * Topological sort using Kahn's algorithm (NORP-005)
   * Complexity: O(V + E)
   */
  private topologicalSort(
    nodes: Node[],
    graph: Record<string, string[]>
  ): string[] {
    // 1. Calculate in-degree for each node
    const inDegree: Record<string, number> = {};

    for (const node of nodes) {
      inDegree[node.id] = 0;
    }

    for (const node of nodes) {
      const dependencies = node.depends_on || [];

      for (const depId of dependencies) {
        if (depId in inDegree) {
          inDegree[node.id]++;
        }
      }
    }

    // 2. Queue with zero in-degree nodes
    // NORP-005: Deterministic tie-breaking (sorted)
    let queue = Object.keys(inDegree)
      .filter(nodeId => inDegree[nodeId] === 0)
      .sort();

    // 3. BFS processing
    const result: string[] = [];

    while (queue.length > 0) {
      const current = queue.shift()!;
      result.push(current);

      const newlyEligible: string[] = [];

      // Find nodes that depend on current
      for (const node of nodes) {
        if (node.depends_on?.includes(current)) {
          inDegree[node.id]--;

          if (inDegree[node.id] === 0) {
            newlyEligible.push(node.id);
          }
        }
      }

      // NORP-005: Deterministic reinsertion (sorted)
      if (newlyEligible.length > 0) {
        queue = [...queue, ...newlyEligible].sort();
      }
    }

    // 4. Verify all nodes sorted (else cycle)
    if (result.length !== nodes.length) {
      throw new Error(
        `Compilation failed: Cycle detected in graph. ` +
        `Nodes sorted: ${result.length}/${nodes.length}`
      );
    }

    return result;
  }

  /**
   * Build dependency graph
   */
  private buildDependencyGraph(nodes: Node[]): Record<string, string[]> {
    const graph: Record<string, string[]> = {};

    for (const node of nodes) {
      graph[node.id] = node.depends_on || [];
    }

    return graph;
  }

  /**
   * Detect parallelizable groups (NORP-005)
   */
  private detectParallelGroups(graph: Record<string, string[]>): ParallelGroup[] {
    const levels = this.computeLevels(graph);
    const groups: ParallelGroup[] = [];

    for (const [level, nodeIds] of Object.entries(levels)) {
      groups.push({
        level: parseInt(level),
        nodes: Object.freeze(nodeIds),
        parallel: nodeIds.length > 1
      });
    }

    return groups;
  }

  /**
   * Compute dependency levels (BFS)
   */
  private computeLevels(graph: Record<string, string[]>): Record<number, string[]> {
    const levels: Record<number, string[]> = {};
    const nodeLevel: Record<string, number> = {};
    const queue: [string, number][] = [];

    // Init: Nodes without dependencies = level 0
    for (const [nodeId, dependencies] of Object.entries(graph)) {
      if (dependencies.length === 0) {
        queue.push([nodeId, 0]);
        nodeLevel[nodeId] = 0;
      }
    }

    // BFS
    while (queue.length > 0) {
      const [current, level] = queue.shift()!;

      if (!levels[level]) {
        levels[level] = [];
      }
      levels[level].push(current);

      // Find nodes that depend on current
      for (const [nodeId, dependencies] of Object.entries(graph)) {
        if (dependencies.includes(current) && !(nodeId in nodeLevel)) {
          // Calculate level = max(dependencies levels) + 1
          const maxDepLevel = Math.max(
            ...dependencies.map(depId => nodeLevel[depId] || 0)
          );

          nodeLevel[nodeId] = maxDepLevel + 1;
          queue.push([nodeId, maxDepLevel + 1]);
        }
      }
    }

    return levels;
  }

  /**
   * Estimate total execution duration
   */
  private estimateDuration(nodes: Node[]): number {
    const durationByType: Record<string, number> = {
      'datasource': 200,
      'llm_call': 2000,
      'custom_code': 100,
      'conditional': 5,
      'loop': 500,
      'output': 50,
    };

    return nodes.reduce((total, node) => {
      return total + (durationByType[node.type] || 100);
    }, 0);
  }
}
