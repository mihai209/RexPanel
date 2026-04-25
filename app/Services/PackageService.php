<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageService
{
    public function create(array $data): Package
    {
        return Package::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
        ]);
    }

    public function update(Package $package, array $data): Package
    {
        $package->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $package->slug,
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
        ]);

        return $package->refresh();
    }

    public function delete(Package $package): void
    {
        $package->loadMissing('images.servers');

        $hasAllocatedServers = $package->images->contains(
            fn ($image) => $image->servers->isNotEmpty()
        );

        if ($hasAllocatedServers) {
            throw new \RuntimeException('Cannot delete package because one or more images have assigned servers.');
        }

        DB::transaction(function () use ($package) {
            $package->images()->delete();
            $package->delete();
        });
    }
}
