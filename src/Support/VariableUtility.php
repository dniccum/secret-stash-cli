<?php

namespace Dniccum\SecretStash\Support;

class VariableUtility
{
    public const RESERVED_PREFIX = 'SECRET_STASH_';

    /**
     * @param  array<int, string>  $ignoredVariables
     */
    public function __construct(protected array $ignoredVariables = []) {}

    /**
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    public function filter(array $variables): array
    {
        return self::filterVariables($variables, $this->ignoredVariables);
    }

    public function isIgnored(string $name): bool
    {
        return self::isIgnoredVariable($name, $this->ignoredVariables);
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<int, string>  $ignoredVariables
     * @return array<string, string>
     */
    public static function filterVariables(array $variables, array $ignoredVariables = []): array
    {
        $filtered = [];
        foreach ($variables as $name => $value) {
            if (self::isIgnoredVariable($name, $ignoredVariables)) {
                continue;
            }

            $filtered[$name] = $value;
        }

        return $filtered;
    }

    /**
     * @param  array<int, string>  $ignoredVariables
     */
    public static function isIgnoredVariable(string $name, array $ignoredVariables = []): bool
    {
        if (str_starts_with($name, self::RESERVED_PREFIX)) {
            return true;
        }

        return in_array($name, $ignoredVariables, true);
    }

    /**
     * @return array<string, string>
     */
    public static function parseEnvContent(string $content): array
    {
        $lines = preg_split('/\\r\\n|\\n|\\r/', $content) ?: [];
        $variables = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (! preg_match('/^\\s*(?:export\\s+)?([^=\\s]+)\\s*=\\s*(.*)\\s*$/', $line, $matches)) {
                continue;
            }

            $variables[$matches[1]] = $matches[2];
        }

        return $variables;
    }

    /**
     * @param  array<string, string>  $variables
     */
    public static function mergeEnvContent(string $content, array $variables): string
    {
        $lineEnding = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = $content === '' ? [] : (preg_split("/\r\n|\n|\r/", $content) ?: []);
        $used = [];

        foreach ($lines as $index => $line) {
            if (! preg_match('/^\\s*(?:export\\s+)?([^=\\s]+)\\s*=\\s*(.*)\\s*$/', $line, $matches)) {
                continue;
            }

            $name = $matches[1];
            if (! array_key_exists($name, $variables)) {
                continue;
            }

            $lines[$index] = $name.'='.$variables[$name];
            $used[$name] = true;
        }

        foreach ($variables as $name => $value) {
            if (isset($used[$name])) {
                continue;
            }

            $lines[] = $name.'='.$value;
        }

        $merged = implode($lineEnding, $lines);
        if ($content !== '' && str_ends_with($content, $lineEnding)) {
            $merged .= $lineEnding;
        }

        return $merged;
    }
}
