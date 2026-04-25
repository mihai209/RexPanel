<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('source_path', 512);
            $table->string('target_path', 512);
            $table->boolean('read_only')->default(false);
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('node_id');
            $table->unique(['node_id', 'name']);
        });

        Schema::create('server_mounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mount_id')->constrained()->cascadeOnDelete();
            $table->boolean('read_only')->default(false);
            $table->timestamps();

            $table->unique(['server_id', 'mount_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_mounts');
        Schema::dropIfExists('mounts');
    }
};
