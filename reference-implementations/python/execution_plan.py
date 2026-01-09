"""
ExecutionPlan - Immutable DTO for compiled execution plan

NORP Compliance:
- NORP-003: Immutable DTO (frozen dataclass)
- NORP-005: Deterministic execution order + parallel groups

License: MIT
Copyright: 2026 NeuraScope CONVERWAY
"""

from dataclasses import dataclass
from typing import List, Dict, Any


@dataclass(frozen=True)
class ExecutionPlan:
    """
    Immutable execution plan (NORP-003 + NORP-005 compliant)
    """
    nodes: List[Dict[str, Any]]
    execution_order: List[str]
    parallel_groups: List[Dict[str, Any]]
    estimated_duration_ms: int

    def get_level(self, level: int) -> List[str]:
        """Get nodes at specific dependency level"""
        if level < len(self.parallel_groups):
            return self.parallel_groups[level].get('nodes', [])
        return []

    def is_parallelizable(self, level: int) -> bool:
        """Check if level can be parallelized"""
        if level >= len(self.parallel_groups):
            return False

        group = self.parallel_groups[level]
        return group.get('parallel', False) and len(group.get('nodes', [])) > 1

    def get_levels_count(self) -> int:
        """Get total number of levels in DAG"""
        return len(self.parallel_groups)

    def get_stats(self) -> dict:
        """Get execution plan statistics"""
        parallelizable_count = sum(
            len(g['nodes'])
            for g in self.parallel_groups
            if g.get('parallel', False)
        )

        return {
            'total_nodes': len(self.nodes),
            'levels': self.get_levels_count(),
            'parallelizable_nodes': parallelizable_count,
            'estimated_duration_ms': self.estimated_duration_ms
        }
