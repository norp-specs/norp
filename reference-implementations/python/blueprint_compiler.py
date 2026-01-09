"""
BlueprintCompiler - NORP-005 Reference Implementation

Implements:
- NORP-005: Deterministic Topological Ordering (Kahn's algorithm)
- NORP-004: Cycle detection via topological sort

License: MIT
Copyright: 2026 NeuraScope CONVERWAY
"""

from typing import List, Dict, Any
from execution_plan import ExecutionPlan


class BlueprintCompiler:
    """
    NORP-compliant workflow compiler
    """

    def compile(self, workflow: dict) -> ExecutionPlan:
        """
        Compile workflow into deterministic execution plan

        Args:
            workflow: Workflow with 'nodes' array

        Returns:
            ExecutionPlan (immutable DTO)

        Raises:
            Exception: If cycle detected
        """
        nodes = workflow.get('nodes', [])

        if not nodes:
            raise Exception('No nodes to compile')

        # 1. Build dependency graph
        graph = self._build_dependency_graph(nodes)

        # 2. Topological sort (NORP-005)
        execution_order = self._topological_sort(nodes, graph)

        # 3. Detect parallelizable groups
        parallel_groups = self._detect_parallel_groups(graph)

        # 4. Estimate duration
        estimated_duration = self._estimate_duration(nodes)

        return ExecutionPlan(
            nodes=nodes,
            execution_order=execution_order,
            parallel_groups=parallel_groups,
            estimated_duration_ms=estimated_duration
        )

    def _topological_sort(self, nodes: List[dict], graph: Dict[str, List[str]]) -> List[str]:
        """
        Topological sort using Kahn's algorithm (NORP-005)

        Complexity: O(V + E)

        Args:
            nodes: List of nodes
            graph: Dependency graph

        Returns:
            Deterministic execution order (node IDs)

        Raises:
            Exception: If cycle detected
        """
        # 1. Calculate in-degree for each node
        in_degree = {}

        for node in nodes:
            node_id = node['id']
            in_degree[node_id] = 0

        for node in nodes:
            node_id = node['id']
            dependencies = node.get('depends_on', [])

            for dep_id in dependencies:
                if dep_id in in_degree:
                    in_degree[node_id] += 1

        # 2. Queue with zero in-degree nodes
        # NORP-005: Deterministic tie-breaking (sorted)
        queue = sorted([
            node_id
            for node_id, degree in in_degree.items()
            if degree == 0
        ])

        # 3. BFS processing
        result = []

        while queue:
            current = queue.pop(0)
            result.append(current)

            newly_eligible = []

            # Find nodes that depend on current
            for node in nodes:
                node_id = node['id']

                if current in node.get('depends_on', []):
                    in_degree[node_id] -= 1

                    if in_degree[node_id] == 0:
                        newly_eligible.append(node_id)

            # NORP-005: Deterministic reinsertion (sorted)
            if newly_eligible:
                queue.extend(newly_eligible)
                queue.sort()

        # 4. Verify all nodes sorted (else cycle)
        if len(result) != len(nodes):
            raise Exception(
                f'Compilation failed: Cycle detected in graph. '
                f'Nodes sorted: {len(result)}/{len(nodes)}'
            )

        return result

    def _build_dependency_graph(self, nodes: List[dict]) -> Dict[str, List[str]]:
        """Build dependency graph"""
        graph = {}

        for node in nodes:
            node_id = node['id']
            graph[node_id] = node.get('depends_on', [])

        return graph

    def _detect_parallel_groups(self, graph: Dict[str, List[str]]) -> List[dict]:
        """
        Detect parallelizable groups (NORP-005)

        Args:
            graph: Dependency graph

        Returns:
            List of parallel groups with levels
        """
        levels = self._compute_levels(graph)
        groups = []

        for level, node_ids in levels.items():
            groups.append({
                'level': level,
                'nodes': node_ids,
                'parallel': len(node_ids) > 1
            })

        return groups

    def _compute_levels(self, graph: Dict[str, List[str]]) -> Dict[int, List[str]]:
        """
        Compute dependency levels (BFS)

        Args:
            graph: Dependency graph

        Returns:
            {'level': ['node1', 'node2']}
        """
        levels = {}
        node_level = {}
        queue = []

        # Init: Nodes without dependencies = level 0
        for node_id, dependencies in graph.items():
            if not dependencies:
                queue.append((node_id, 0))
                node_level[node_id] = 0

        # BFS
        while queue:
            current, level = queue.pop(0)

            if level not in levels:
                levels[level] = []
            levels[level].append(current)

            # Find nodes that depend on current
            for node_id, dependencies in graph.items():
                if current in dependencies and node_id not in node_level:
                    # Calculate level = max(dependencies levels) + 1
                    max_dep_level = max(
                        (node_level.get(dep_id, 0) for dep_id in dependencies),
                        default=0
                    )

                    node_level[node_id] = max_dep_level + 1
                    queue.append((node_id, max_dep_level + 1))

        return levels

    def _estimate_duration(self, nodes: List[dict]) -> int:
        """
        Estimate total execution duration

        Args:
            nodes: List of nodes

        Returns:
            Duration in milliseconds
        """
        duration_by_type = {
            'datasource': 200,
            'llm_call': 2000,
            'custom_code': 100,
            'conditional': 5,
            'loop': 500,
            'output': 50,
        }

        total_duration = sum(
            duration_by_type.get(node.get('type', 'unknown'), 100)
            for node in nodes
        )

        return total_duration
