<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('package_id')->constrained('packages');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('docker_image');
            $table->json('docker_images')->nullable();
            $table->json('features')->nullable();
            $table->json('file_denylist')->nullable();
            $table->longText('startup')->nullable();
            $table->longText('config_files')->nullable();
            $table->longText('config_startup')->nullable();
            $table->longText('config_logs')->nullable();
            $table->string('config_stop')->nullable();
            $table->longText('script_install')->nullable();
            $table->string('script_entry')->nullable();
            $table->string('script_container')->nullable();
            $table->json('variables')->nullable();
            $table->string('source_path')->nullable()->unique();
            $table->string('source_hash', 64)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
