<?php

namespace App\Services;

use App\Models\Image;
use App\Models\ImageVariable;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImageService
{
    public function create(Package $package, array $data): Image
    {
        return DB::transaction(function () use ($package, $data) {
            $image = Image::create([
                'id' => (string) Str::uuid(),
                'package_id' => $package->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'author' => $data['author'] ?? null,
                'update_url' => $data['update_url'] ?? null,
                'is_public' => (bool) ($data['is_public'] ?? true),
                'docker_image' => $data['docker_image'],
                'docker_images' => $data['docker_images'] ?? [$data['docker_image'] => $data['docker_image']],
                'features' => $data['features'] ?? [],
                'file_denylist' => $data['file_denylist'] ?? [],
                'startup' => $data['startup'] ?? null,
                'config_files' => $data['config_files'] ?? null,
                'config_startup' => $data['config_startup'] ?? null,
                'config_logs' => $data['config_logs'] ?? null,
                'config_stop' => $data['config_stop'] ?? null,
                'script_install' => $data['script_install'] ?? null,
                'script_entry' => $data['script_entry'] ?? null,
                'script_container' => $data['script_container'] ?? null,
                'variables' => $data['variables'] ?? [],
                'source_path' => $data['source_path'] ?? null,
                'source_hash' => $data['source_hash'] ?? null,
                'imported_at' => $data['imported_at'] ?? null,
            ]);

            $this->syncVariables($image, $data['variables'] ?? []);

            return $image->fresh(['package', 'imageVariables']);
        });
    }

    public function updateConfiguration(Image $image, array $data): Image
    {
        $payload = [
            'package_id' => $data['package_id'] ?? $image->package_id,
            'name' => $data['name'] ?? $image->name,
            'description' => $data['description'] ?? $image->description,
            'docker_image' => $data['docker_image'] ?? $image->docker_image,
            'docker_images' => $data['docker_images'] ?? $image->docker_images,
            'features' => $data['features'] ?? $image->features,
            'file_denylist' => $data['file_denylist'] ?? $image->file_denylist,
            'startup' => $data['startup'] ?? $image->startup,
            'config_files' => $data['config_files'] ?? $image->config_files,
            'config_startup' => $data['config_startup'] ?? $image->config_startup,
            'config_logs' => $data['config_logs'] ?? $image->config_logs,
            'config_stop' => $data['config_stop'] ?? $image->config_stop,
            'is_public' => array_key_exists('is_public', $data) ? (bool) $data['is_public'] : $image->is_public,
        ];

        $image->update($payload);

        return $image->fresh(['package', 'imageVariables']);
    }

    public function updateScripts(Image $image, array $data): Image
    {
        $image->update([
            'script_install' => $data['script_install'] ?? null,
            'script_entry' => $data['script_entry'] ?? null,
            'script_container' => $data['script_container'] ?? null,
        ]);

        return $image->fresh(['package', 'imageVariables']);
    }

    public function replaceFromImport(Image $image, array $normalized): Image
    {
        return DB::transaction(function () use ($image, $normalized) {
            $image->update([
                'package_id' => $normalized['package_id'] ?? $image->package_id,
                'name' => $normalized['name'],
                'description' => $normalized['description'],
                'author' => $normalized['author'],
                'update_url' => $normalized['update_url'] ?? null,
                'is_public' => array_key_exists('is_public', $normalized) ? (bool) $normalized['is_public'] : $image->is_public,
                'docker_image' => $normalized['docker_image'],
                'docker_images' => $normalized['docker_images'],
                'features' => $normalized['features'],
                'file_denylist' => $normalized['file_denylist'],
                'startup' => $normalized['startup'],
                'config_files' => $normalized['config_files'],
                'config_startup' => $normalized['config_startup'],
                'config_logs' => $normalized['config_logs'],
                'config_stop' => $normalized['config_stop'],
                'script_install' => $normalized['script_install'],
                'script_entry' => $normalized['script_entry'],
                'script_container' => $normalized['script_container'],
                'variables' => $normalized['variables'],
                'source_hash' => $normalized['source_hash'] ?? $image->source_hash,
                'imported_at' => now(),
            ]);

            $this->syncVariables($image, $normalized['variables'] ?? []);

            return $image->fresh(['package', 'imageVariables']);
        });
    }

    public function delete(Image $image): void
    {
        if ($image->servers()->exists()) {
            throw new \RuntimeException('Cannot delete image because it is assigned to one or more servers.');
        }

        $image->delete();
    }

    public function createVariable(Image $image, array $data): ImageVariable
    {
        $variable = $image->imageVariables()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'env_variable' => $data['env_variable'],
            'default_value' => $data['default_value'] ?? null,
            'user_viewable' => (bool) ($data['user_viewable'] ?? true),
            'user_editable' => (bool) ($data['user_editable'] ?? true),
            'rules' => $data['rules'] ?? null,
            'field_type' => $data['field_type'] ?? 'text',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->mirrorVariablesJson($image);

        return $variable;
    }

    public function updateVariable(ImageVariable $variable, array $data): ImageVariable
    {
        $variable->update([
            'name' => $data['name'] ?? $variable->name,
            'description' => $data['description'] ?? $variable->description,
            'env_variable' => $data['env_variable'] ?? $variable->env_variable,
            'default_value' => $data['default_value'] ?? $variable->default_value,
            'user_viewable' => array_key_exists('user_viewable', $data) ? (bool) $data['user_viewable'] : $variable->user_viewable,
            'user_editable' => array_key_exists('user_editable', $data) ? (bool) $data['user_editable'] : $variable->user_editable,
            'rules' => $data['rules'] ?? $variable->rules,
            'field_type' => $data['field_type'] ?? $variable->field_type,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $variable->sort_order,
        ]);

        $this->mirrorVariablesJson($variable->image);

        return $variable->fresh();
    }

    public function deleteVariable(ImageVariable $variable): void
    {
        $image = $variable->image;
        $variable->delete();
        $this->mirrorVariablesJson($image);
    }

    public function syncVariables(Image $image, array $variables): void
    {
        $image->imageVariables()->delete();

        foreach (array_values($variables) as $index => $variable) {
            if (!is_array($variable) || empty($variable['env_variable'])) {
                continue;
            }

            $image->imageVariables()->create([
                'name' => $variable['name'] ?? $variable['env_variable'],
                'description' => $variable['description'] ?? null,
                'env_variable' => $variable['env_variable'],
                'default_value' => $variable['default_value'] ?? null,
                'user_viewable' => array_key_exists('user_viewable', $variable) ? (bool) $variable['user_viewable'] : true,
                'user_editable' => array_key_exists('user_editable', $variable) ? (bool) $variable['user_editable'] : true,
                'rules' => $variable['rules'] ?? null,
                'field_type' => $variable['field_type'] ?? 'text',
                'sort_order' => $variable['sort_order'] ?? $index,
            ]);
        }

        $this->mirrorVariablesJson($image);
    }

    private function mirrorVariablesJson(Image $image): void
    {
        $variables = $image->imageVariables()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($variable) => [
                'name' => $variable->name,
                'description' => $variable->description,
                'env_variable' => $variable->env_variable,
                'default_value' => $variable->default_value,
                'user_viewable' => $variable->user_viewable,
                'user_editable' => $variable->user_editable,
                'rules' => $variable->rules,
                'field_type' => $variable->field_type,
            ])
            ->values()
            ->all();

        $image->forceFill(['variables' => $variables])->save();
    }
}
