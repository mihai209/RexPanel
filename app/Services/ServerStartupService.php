<?php

namespace App\Services;

use App\Models\Image;
use App\Models\NodeAllocation;
use App\Models\Server;

class ServerStartupService
{
    private const OPTIONAL_PLACEHOLDERS = ['STARTUP', 'STARTUPSCRIPT'];

    public function normalizeVariables(mixed $rawVariables): array
    {
        if (is_string($rawVariables)) {
            $decoded = json_decode($rawVariables, true);
            $rawVariables = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($rawVariables)) {
            return [];
        }

        $normalized = [];
        foreach ($rawVariables as $key => $value) {
            $normalized[(string) $key] = $value === null ? '' : (string) $value;
        }

        return $normalized;
    }

    public function resolveVariableDefinitions(Image $image): array
    {
        return $image->imageVariables
            ->map(fn ($variable) => [
                'env_variable' => $variable->env_variable,
                'default_value' => $variable->default_value,
                'name' => $variable->name,
                'description' => $variable->description,
                'rules' => $variable->rules,
                'user_editable' => (bool) $variable->user_editable,
            ])
            ->all();
    }

    public function buildEnvironment(Server|array $server, Image $image, NodeAllocation $allocation): array
    {
        $data = $server instanceof Server ? $server->toArray() : $server;
        $defaults = collect($this->resolveVariableDefinitions($image))
            ->mapWithKeys(fn ($variable) => [$variable['env_variable'] => (string) ($variable['default_value'] ?? '')])
            ->all();

        $variables = array_merge($defaults, $this->normalizeVariables($data['variables'] ?? []));
        $variables['SERVER_MEMORY'] = (string) ($data['memory'] ?? '');
        $variables['SERVER_IP'] = $allocation->ip;
        $variables['SERVER_PORT'] = (string) $allocation->port;

        return $variables;
    }

    public function buildStartupCommand(string $startupTemplate, array $environment): string
    {
        if (trim($startupTemplate) === '') {
            throw new \RuntimeException('Image startup command is missing.');
        }

        $sourceEnv = $this->normalizeVariables($environment);
        $ciEnv = [];
        foreach ($sourceEnv as $key => $value) {
            $normalized = strtoupper(trim((string) $key));
            if ($normalized !== '') {
                $ciEnv[$normalized] = $value;
            }
        }

        if (isset($ciEnv['STARTUP']) && !isset($ciEnv['STARTUPSCRIPT'])) {
            $ciEnv['STARTUPSCRIPT'] = $ciEnv['STARTUP'];
        }

        if (isset($ciEnv['STARTUPSCRIPT']) && !isset($ciEnv['STARTUP'])) {
            $ciEnv['STARTUP'] = $ciEnv['STARTUPSCRIPT'];
        }

        $replaceValue = function (array $match) use ($sourceEnv, $ciEnv) {
            $key = $match[1];
            if (array_key_exists($key, $sourceEnv)) {
                return $sourceEnv[$key];
            }

            $normalizedKey = strtoupper(trim($key));
            if (array_key_exists($normalizedKey, $ciEnv)) {
                return $ciEnv[$normalizedKey];
            }

            if (in_array($normalizedKey, self::OPTIONAL_PLACEHOLDERS, true)) {
                return '';
            }

            return $match[0];
        };

        $startup = preg_replace_callback('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $replaceValue, $startupTemplate);
        $startup = preg_replace_callback('/(?<!\$)\{([A-Za-z0-9_]+)\}/', $replaceValue, $startup);

        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}|(?<!\$)\{([A-Za-z0-9_]+)\}/', $startup, $matches);
        $unresolved = collect(array_merge($matches[1] ?? [], $matches[2] ?? []))
            ->filter()
            ->map(fn ($key) => strtoupper(trim((string) $key)))
            ->reject(fn ($key) => in_array($key, self::OPTIONAL_PLACEHOLDERS, true))
            ->unique()
            ->values()
            ->all();

        if ($unresolved !== []) {
            throw new \RuntimeException('Startup contains unresolved placeholders: ' . implode(', ', $unresolved));
        }

        return $startup;
    }

    public function buildPreview(Server|array $server, Image $image, NodeAllocation $allocation): array
    {
        $environment = $this->buildEnvironment($server, $image, $allocation);
        $template = (string) (($server instanceof Server ? $server->startup : ($server['startup'] ?? null)) ?: $image->startup ?: '');

        return [
            'template' => $template,
            'resolved' => $this->buildStartupCommand($template, $environment),
            'environment' => $environment,
            'docker_image' => (string) (($server instanceof Server ? $server->docker_image : ($server['docker_image'] ?? null)) ?: $image->docker_image ?: ''),
        ];
    }
}
