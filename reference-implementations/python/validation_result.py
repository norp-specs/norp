"""
ValidationResult - Immutable DTO for validation results

NORP Compliance:
- NORP-003: Immutable DTO (frozen dataclass)
- NORP-001: Validation result structure
- NORP-007: Cost estimation included

License: MIT
Copyright: 2026 NeuraScope CONVERWAY
"""

from dataclasses import dataclass
from typing import List


@dataclass(frozen=True)
class ValidationResult:
    """
    Immutable validation result (NORP-003 compliant)
    """
    valid: bool
    errors: List[str]
    warnings: List[str]
    estimated_cost: float = 0.0

    def has_critical_errors(self) -> bool:
        """Check if critical errors are present"""
        return not self.valid and len(self.errors) > 0

    def to_dict(self) -> dict:
        """Convert to dictionary for serialization"""
        return {
            'valid': self.valid,
            'errors': self.errors,
            'warnings': self.warnings,
            'estimated_cost': self.estimated_cost
        }

    def get_summary(self) -> str:
        """Get summary message"""
        if self.valid:
            msg = 'Validation passed'
            if self.warnings:
                msg += f' ({len(self.warnings)} warnings)'
            return msg

        return 'Validation failed: ' + ', '.join(self.errors)
