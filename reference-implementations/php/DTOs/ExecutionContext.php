<?php

namespace NORP\PHP\DTOs;

/**
 * ExecutionContext - Immutable execution context with resource pooling
 *
 * NORP Compliance:
 * - NORP-003: Immutable DTO (readonly properties)
 * - NORP-006: Execution-scoped resource isolation
 * - NORP-002: Tenant isolation (tenant_id scoping)
 *
 * @license MIT
 * @copyright 2026 NeuraScope CONVERWAY
 */
readonly class ExecutionContext
{
    public function __construct(
        public string $tenant_id,
        public string $blueprint_id,
        public string $execution_id,
        public array $inputs,
        public array $variables,
        public array $datasources,
        public array $llm_servers,
        public mixed $cache,
        public array $secrets,
        public string $started_at,
        public mixed $previous_result = null,
    ) {}

    /**
     * Interpolate variables in text
     *
     * Pattern: {{source.field}}
     * Sources: input, variable, previous
     *
     * @param string $text - Text with variables
     * @param array $additionalContext - Additional context (e.g., previous result)
     * @return string - Interpolated text
     */
    public function interpolate(string $text, array $additionalContext = []): string
    {
        // Extract credentials from datasources
        $datasourceCredentials = [];
        foreach ($this->datasources as $dsId => $ds) {
            if (isset($ds['credentials'])) {
                $datasourceCredentials = array_merge($datasourceCredentials, $ds['credentials']);
            }
        }

        $allContext = array_merge([
            'input' => $this->inputs,
            'variable' => $this->variables,
            'credential' => $datasourceCredentials,
        ], $additionalContext);

        return preg_replace_callback('/\{\{(\w+)\.(\w+)\}\}/', function($matches) use ($allContext) {
            $source = $matches[1];
            $field = $matches[2];

            return $allContext[$source][$field] ?? $matches[0];
        }, $text);
    }

    /**
     * Resolve variable path with dot notation
     *
     * Example: "input.user.email" → $this->inputs['user']['email']
     *
     * @param string $path - Variable path (dot notation)
     * @param mixed $data - Additional data (previous result)
     * @return mixed
     */
    public function resolveVariablePath(string $path, $data = null)
    {
        $parts = explode('.', $path);
        $source = array_shift($parts);

        $value = match($source) {
            'input' => $this->inputs,
            'variable' => $this->variables,
            'previous' => $data,
            default => null
        };

        foreach ($parts as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->$key;
            } else {
                return null;
            }
        }

        return $value;
    }
}
