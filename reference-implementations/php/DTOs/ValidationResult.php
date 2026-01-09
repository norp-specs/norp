<?php

namespace NORP\PHP\DTOs;

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
readonly class ValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public float $estimated_cost = 0.0,
    ) {}

    /**
     * Check if critical errors are present
     *
     * @return bool
     */
    public function hasCriticalErrors(): bool
    {
        return !$this->valid && !empty($this->errors);
    }

    /**
     * Convert to array for serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'estimated_cost' => $this->estimated_cost,
        ];
    }

    /**
     * Get summary message
     *
     * @return string
     */
    public function getSummary(): string
    {
        if ($this->valid) {
            $msg = 'Validation passed';
            if (!empty($this->warnings)) {
                $msg .= ' (' . count($this->warnings) . ' warnings)';
            }
            return $msg;
        }

        return 'Validation failed: ' . implode(', ', $this->errors);
    }
}
