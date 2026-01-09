/**
 * ValidationResult - Immutable DTO for validation results
 *
 * NORP Compliance:
 * - NORP-003: Immutable DTO (readonly properties)
 * - NORP-001: Validation result structure
 * - NORP-007: Cost estimation included
 *
 * @license MIT
 * @copyright 2026 NeuraScope CONVERWAY
 */

export interface ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;
  readonly warnings: ReadonlyArray<string>;
  readonly estimated_cost: number;
}

export class ValidationResultImpl implements ValidationResult {
  readonly valid: boolean;
  readonly errors: ReadonlyArray<string>;
  readonly warnings: ReadonlyArray<string>;
  readonly estimated_cost: number;

  constructor(
    valid: boolean,
    errors: string[] = [],
    warnings: string[] = [],
    estimated_cost: number = 0.0
  ) {
    this.valid = valid;
    this.errors = Object.freeze([...errors]);
    this.warnings = Object.freeze([...warnings]);
    this.estimated_cost = estimated_cost;

    // NORP-003: Deep immutability
    Object.freeze(this);
  }

  hasCriticalErrors(): boolean {
    return !this.valid && this.errors.length > 0;
  }

  toJSON(): object {
    return {
      valid: this.valid,
      errors: this.errors,
      warnings: this.warnings,
      estimated_cost: this.estimated_cost
    };
  }

  getSummary(): string {
    if (this.valid) {
      let msg = 'Validation passed';
      if (this.warnings.length > 0) {
        msg += ` (${this.warnings.length} warnings)`;
      }
      return msg;
    }

    return 'Validation failed: ' + this.errors.join(', ');
  }
}
