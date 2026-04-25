<?php

namespace App\Services;

use App\Models\Image;

class ImageExportService
{
    public function handle(Image $image): string
    {
        $image->loadMissing('imageVariables');

        $struct = [
            '_comment' => 'DO NOT EDIT: FILE GENERATED AUTOMATICALLY BY RA-panel',
            'meta' => [
                'version' => 'PTDL_v2',
                'update_url' => $image->update_url,
            ],
            'exported_at' => now()->toAtomString(),
            'name' => $image->name,
            'author' => $image->author,
            'description' => $image->description,
            'features' => $image->features ?? [],
            'docker_images' => $image->docker_images ?? [],
            'file_denylist' => $image->file_denylist ?? [],
            'startup' => $image->startup,
            'config' => [
                'files' => $this->decodeJsonField($image->config_files),
                'startup' => $this->decodeJsonField($image->config_startup),
                'logs' => $this->decodeJsonField($image->config_logs),
                'stop' => $image->config_stop,
            ],
            'scripts' => [
                'installation' => [
                    'script' => $image->script_install,
                    'container' => $image->script_container,
                    'entrypoint' => $image->script_entry,
                ],
            ],
            'variables' => $image->imageVariables
                ->sortBy('sort_order')
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
                ->all(),
        ];

        return json_encode($struct, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function decodeJsonField(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
