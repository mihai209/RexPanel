<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Package;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EggImportService
{
    private const SUPPORTED_VERSIONS = ['PTDL_v1', 'PTDL_v2'];

    public function __construct(private ImageService $imageService)
    {
    }

    public function parseEggJson(array $rawParsed): array
    {
        $parsed = isset($rawParsed['attributes']) && is_array($rawParsed['attributes'])
            ? $rawParsed['attributes']
            : $rawParsed;

        $version = Arr::get($parsed, 'meta.version');
        if (!in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw new \InvalidArgumentException('The JSON file provided is not in a recognized egg format.');
        }

        $normalized = $version === 'PTDL_v1'
            ? $this->convertV1ToV2($parsed)
            : $parsed;

        $name = trim((string) Arr::get($normalized, 'name', ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Imported JSON is missing "name".');
        }

        $dockerImages = $this->normalizeDockerImages(Arr::get($normalized, 'docker_images'));
        if (count($dockerImages) < 1) {
            throw new \InvalidArgumentException('Imported JSON must include docker_images.');
        }

        $dockerImage = reset($dockerImages);
        if (!is_string($dockerImage) || trim($dockerImage) === '') {
            throw new \InvalidArgumentException('Could not resolve docker_image from docker_images.');
        }

        $variables = Arr::get($normalized, 'variables', []);
        $variables = is_array($variables) ? array_values($variables) : [];

        $features = Arr::get($normalized, 'features', []);
        $features = is_array($features) ? array_values($features) : [];

        $fileDenylist = Arr::get($normalized, 'file_denylist');
        $fileDenylist = is_array($fileDenylist)
            ? array_values(array_filter($fileDenylist, fn ($value) => is_string($value) && trim($value) !== ''))
            : [];

        return [
            'name' => $name,
            'description' => Arr::get($normalized, 'description'),
            'author' => Arr::get($normalized, 'author'),
            'update_url' => Arr::get($normalized, 'meta.update_url'),
            'is_public' => true,
            'docker_image' => trim($dockerImage),
            'docker_images' => $dockerImages,
            'features' => $features,
            'file_denylist' => $fileDenylist,
            'startup' => Arr::get($normalized, 'startup'),
            'config_files' => $this->encodeJsonField(Arr::get($normalized, 'config.files')),
            'config_startup' => $this->encodeJsonField(Arr::get($normalized, 'config.startup')),
            'config_logs' => $this->encodeJsonField(Arr::get($normalized, 'config.logs')),
            'config_stop' => Arr::get($normalized, 'config.stop'),
            'script_install' => Arr::get($normalized, 'scripts.installation.script'),
            'script_entry' => Arr::get($normalized, 'scripts.installation.entrypoint'),
            'script_container' => Arr::get($normalized, 'scripts.installation.container'),
            'variables' => $variables,
        ];
    }

    public function importFromPayload(array $payloadItems, int $packageId, bool $isPublic = true): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        $package = Package::findOrFail($packageId);

        foreach ($payloadItems as $index => $item) {
            try {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Entry is not a JSON object.');
                }

                $normalized = $this->parseEggJson($item);
                $normalized['package_id'] = $package->id;
                $normalized['is_public'] = $isPublic;
                $normalized['imported_at'] = now();
                $normalized['source_hash'] = hash('sha256', json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                $image = Image::query()
                    ->where('package_id', $package->id)
                    ->where('name', $normalized['name'])
                    ->first();

                if ($image) {
                    $this->imageService->replaceFromImport($image, $normalized);
                    $updated++;
                } else {
                    $this->imageService->create($package, $normalized);
                    $created++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'entry' => $index + 1,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return compact('created', 'updated', 'failed', 'errors');
    }

    private function convertV1ToV2(array $parsed): array
    {
        $images = [];

        if (isset($parsed['images']) && is_array($parsed['images'])) {
            $images = $parsed['images'];
        } elseif (isset($parsed['image'])) {
            $images = [$parsed['image']];
        }

        $parsed['docker_images'] = [];
        foreach ($images as $image) {
            $image = trim((string) $image);
            if ($image !== '') {
                $parsed['docker_images'][$image] = $image;
            }
        }

        $variables = is_array($parsed['variables'] ?? null) ? $parsed['variables'] : [];
        $parsed['variables'] = array_map(function ($value) {
            if (!is_array($value)) {
                return ['field_type' => 'text'];
            }

            if (!isset($value['field_type'])) {
                $value['field_type'] = 'text';
            }

            return $value;
        }, $variables);

        unset($parsed['image'], $parsed['images']);

        return $parsed;
    }

    private function normalizeDockerImages(mixed $dockerImages): array
    {
        if (!is_array($dockerImages)) {
            return [];
        }

        $normalized = [];
        foreach ($dockerImages as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $label = is_string($key) ? trim($key) : $value;
            $normalized[$label === '' ? $value : $label] = $value;
        }

        return $normalized;
    }

    private function encodeJsonField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

}
