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

import { ValidationResultImpl } from './ValidationResult';
import type { ValidationResult, Node } from './types';

export class BlueprintValidator {
  /**
   * Validate complete workflow
   */
  validate(
    workflow: { nodes: Node[] },
    resourceValidator?: (node: Node) => string[]
  ): ValidationResult {
    const errors: string[] = [];
    const warnings: string[] = [];

    // 1. Structural validation
    if (!workflow.nodes || workflow.nodes.length === 0) {
      errors.push('At least one node required in workflow');
      return new ValidationResultImpl(false, errors, warnings);
    }

    // 2. Cycle detection (NORP-004)
    if (this.detectCycles(workflow.nodes)) {
      errors.push('Cycle detected in execution graph');
    }

    // 3. Validate node dependencies
    for (const node of workflow.nodes) {
      const nodeId = node.id || 'unknown';

      if (node.depends_on) {
        for (const depId of node.depends_on) {
          if (!this.nodeExists(workflow.nodes, depId)) {
            errors.push(
              `Node '${nodeId}' depends on non-existent node '${depId}'`
            );
          }
        }
      }
    }

    // 4. Validate resources (if validator provided)
    if (resourceValidator) {
      for (const node of workflow.nodes) {
        const resourceErrors = resourceValidator(node);
        errors.push(...resourceErrors);
      }
    }

    // 5. Estimate cost (NORP-007)
    const estimatedCost = this.estimateCost(workflow.nodes);

    if (estimatedCost > 100) {
      warnings.push(
        `High estimated cost: $${estimatedCost.toFixed(2)} ` +
        '(based on 1K executions/month)'
      );
    }

    return new ValidationResultImpl(
      errors.length === 0,
      errors,
      warnings,
      estimatedCost
    );
  }

  /**
   * Detect cycles using DFS (NORP-004)
   * Complexity: O(V + E)
   */
  private detectCycles(nodes: Node[]): boolean {
    const graph = this.buildGraph(nodes);
    const visited = new Set<string>();
    const recStack = new Set<string>();

    for (const nodeId of Object.keys(graph)) {
      if (this.isCyclicUtil(nodeId, graph, visited, recStack)) {
        return true;
      }
    }

    return false;
  }

  /**
   * DFS recursive cycle detection
   */
  private isCyclicUtil(
    nodeId: string,
    graph: Record<string, string[]>,
    visited: Set<string>,
    recStack: Set<string>
  ): boolean {
    // Back-edge detected → CYCLE
    if (recStack.has(nodeId)) {
      return true;
    }

    // Already fully explored
    if (visited.has(nodeId)) {
      return false;
    }

    // Mark as visiting
    visited.add(nodeId);
    recStack.add(nodeId);

    // Explore neighbors
    for (const neighbor of graph[nodeId] || []) {
      if (this.isCyclicUtil(neighbor, graph, visited, recStack)) {
        return true;
      }
    }

    // Backtrack
    recStack.delete(nodeId);

    return false;
  }

  /**
   * Build dependency graph
   */
  private buildGraph(nodes: Node[]): Record<string, string[]> {
    const graph: Record<string, string[]> = {};

    // Initialize all nodes
    for (const node of nodes) {
      const nodeId = node.id || `node_${Date.now()}`;
      graph[nodeId] = [];
    }

    // Add edges (inverted for DFS)
    for (const node of nodes) {
      const nodeId = node.id || `node_${Date.now()}`;
      const dependencies = node.depends_on || [];

      for (const depId of dependencies) {
        if (!graph[depId]) {
          graph[depId] = [];
        }
        graph[depId].push(nodeId);
      }
    }

    return graph;
  }

  /**
   * Check if node exists
   */
  private nodeExists(nodes: Node[], nodeId: string): boolean {
    return nodes.some(node => node.id === nodeId);
  }

  /**
   * Estimate workflow cost (NORP-007)
   */
  private estimateCost(nodes: Node[]): number {
    let totalCost = 0;

    for (const node of nodes) {
      if (node.type === 'llm_call') {
        const config = node.config || {};
        const maxTokens = config.max_tokens || 1000;
        const model = config.model || 'gpt-3.5-turbo';

        const pricing = this.getModelPricing(model);

        // NORP-007: Token estimation (chars / 4 for English)
        const prompt = config.prompt || '';
        const inputTokens = prompt.length / 4;

        // NORP-007: Cost formula
        const costPerExecution =
          (inputTokens / 1000 * pricing.input) +
          (maxTokens / 1000 * pricing.output);

        totalCost += costPerExecution;
      }
    }

    // NORP-007: Conservative estimation (30% margin)
    return parseFloat((totalCost * 1.3).toFixed(4));
  }

  /**
   * Get model pricing (NORP-007 Appendix B)
   */
  private getModelPricing(model: string): { input: number; output: number } {
    const pricingMap: Record<string, { input: number; output: number }> = {
      'claude-3-5-sonnet': { input: 0.003, output: 0.015 },
      'claude-3-haiku': { input: 0.00025, output: 0.00125 },
      'gpt-4-turbo': { input: 0.010, output: 0.030 },
      'gpt-3.5-turbo': { input: 0.0005, output: 0.0015 },
      'mistral-large': { input: 0.004, output: 0.012 },
      'llama': { input: 0.000, output: 0.000 },
    };

    const modelLower = model.toLowerCase();
    for (const [key, pricing] of Object.entries(pricingMap)) {
      if (modelLower.includes(key)) {
        return pricing;
      }
    }

    // Default: average pricing
    return { input: 0.010, output: 0.030 };
  }
}
