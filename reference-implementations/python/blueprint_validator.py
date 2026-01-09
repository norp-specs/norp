"""
BlueprintValidator - NORP-001 and NORP-004 Reference Implementation

Implements:
- NORP-001: Pre-Execution Validation Pipeline (Structural Validation stage)
- NORP-004: Cycle Detection (DFS algorithm O(V+E))
- NORP-007: Cost Estimation

License: MIT
Copyright: 2026 NeuraScope CONVERWAY
"""

from typing import Dict, List, Set, Callable, Optional
from validation_result import ValidationResult


class BlueprintValidator:
    """
    NORP-compliant workflow validator
    """

    def validate(
        self,
        workflow: dict,
        resource_validator: Optional[Callable] = None
    ) -> ValidationResult:
        """
        Validate complete workflow

        Args:
            workflow: Workflow definition with 'nodes' array
            resource_validator: Optional callback to validate resource existence

        Returns:
            ValidationResult (immutable DTO)
        """
        errors = []
        warnings = []

        # 1. Structural validation
        if not workflow.get('nodes'):
            errors.append('At least one node required in workflow')
            return ValidationResult(
                valid=False,
                errors=errors,
                warnings=warnings
            )

        # 2. Cycle detection (NORP-004)
        if self._detect_cycles(workflow['nodes']):
            errors.append('Cycle detected in execution graph')

        # 3. Validate node dependencies
        for node in workflow['nodes']:
            node_id = node.get('id', 'unknown')

            if node.get('depends_on'):
                for dep_id in node['depends_on']:
                    if not self._node_exists(workflow['nodes'], dep_id):
                        errors.append(
                            f"Node '{node_id}' depends on non-existent node '{dep_id}'"
                        )

        # 4. Validate resources (if validator provided)
        if resource_validator:
            for node in workflow['nodes']:
                resource_errors = resource_validator(node)
                errors.extend(resource_errors)

        # 5. Estimate cost (NORP-007)
        estimated_cost = self._estimate_cost(workflow['nodes'])

        if estimated_cost > 100:
            warnings.append(
                f"High estimated cost: ${estimated_cost:.2f} "
                "(based on 1K executions/month)"
            )

        return ValidationResult(
            valid=len(errors) == 0,
            errors=errors,
            warnings=warnings,
            estimated_cost=estimated_cost
        )

    def _detect_cycles(self, nodes: List[dict]) -> bool:
        """
        Detect cycles using DFS (NORP-004)

        Complexity: O(V + E)

        Args:
            nodes: List of node dictionaries

        Returns:
            True if cycle detected
        """
        graph = self._build_graph(nodes)
        visited: Set[str] = set()
        rec_stack: Set[str] = set()

        for node_id in graph.keys():
            if self._is_cyclic_util(node_id, graph, visited, rec_stack):
                return True

        return False

    def _is_cyclic_util(
        self,
        node_id: str,
        graph: Dict[str, List[str]],
        visited: Set[str],
        rec_stack: Set[str]
    ) -> bool:
        """
        DFS recursive cycle detection

        Args:
            node_id: Current node
            graph: Dependency graph
            visited: Fully explored nodes
            rec_stack: Recursion stack (detects back-edge)

        Returns:
            True if cycle detected
        """
        # Back-edge detected → CYCLE
        if node_id in rec_stack:
            return True

        # Already fully explored
        if node_id in visited:
            return False

        # Mark as visiting
        visited.add(node_id)
        rec_stack.add(node_id)

        # Explore neighbors
        for neighbor in graph.get(node_id, []):
            if self._is_cyclic_util(neighbor, graph, visited, rec_stack):
                return True

        # Backtrack
        rec_stack.remove(node_id)

        return False

    def _build_graph(self, nodes: List[dict]) -> Dict[str, List[str]]:
        """
        Build dependency graph

        Args:
            nodes: List of nodes

        Returns:
            Graph as {'node_id': ['dependent_node_1', 'dependent_node_2']}
        """
        graph = {}

        # Initialize all nodes
        for node in nodes:
            node_id = node.get('id', f'node_{id(node)}')
            graph[node_id] = []

        # Add edges (inverted for DFS)
        for node in nodes:
            node_id = node.get('id', f'node_{id(node)}')
            dependencies = node.get('depends_on', [])

            for dep_id in dependencies:
                if dep_id not in graph:
                    graph[dep_id] = []
                graph[dep_id].append(node_id)

        return graph

    def _node_exists(self, nodes: List[dict], node_id: str) -> bool:
        """Check if node exists"""
        return any(node.get('id') == node_id for node in nodes)

    def _estimate_cost(self, nodes: List[dict]) -> float:
        """
        Estimate workflow cost (NORP-007)

        Args:
            nodes: List of nodes

        Returns:
            Estimated cost in USD
        """
        total_cost = 0.0

        for node in nodes:
            if node.get('type') == 'llm_call':
                config = node.get('config', {})
                max_tokens = config.get('max_tokens', 1000)
                model = config.get('model', 'gpt-3.5-turbo')

                pricing = self._get_model_pricing(model)

                # NORP-007: Token estimation (chars / 4 for English)
                prompt = config.get('prompt', '')
                input_tokens = len(prompt) / 4

                # NORP-007: Cost formula
                cost_per_execution = (
                    (input_tokens / 1000 * pricing['input']) +
                    (max_tokens / 1000 * pricing['output'])
                )

                total_cost += cost_per_execution

        # NORP-007: Conservative estimation (30% margin)
        return round(total_cost * 1.3, 4)

    def _get_model_pricing(self, model: str) -> dict:
        """
        Get model pricing (NORP-007 Appendix B)

        Args:
            model: Model name

        Returns:
            {'input': float, 'output': float} ($/1K tokens)
        """
        pricing_map = {
            'claude-3-5-sonnet': {'input': 0.003, 'output': 0.015},
            'claude-3-haiku': {'input': 0.00025, 'output': 0.00125},
            'gpt-4-turbo': {'input': 0.010, 'output': 0.030},
            'gpt-3.5-turbo': {'input': 0.0005, 'output': 0.0015},
            'mistral-large': {'input': 0.004, 'output': 0.012},
            'llama': {'input': 0.000, 'output': 0.000},
        }

        model_lower = model.lower()
        for key, pricing in pricing_map.items():
            if key in model_lower:
                return pricing

        # Default: average pricing
        return {'input': 0.010, 'output': 0.030}
