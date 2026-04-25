<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_runtime_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('power_state')->nullable();
            $table->string('install_state')->nullable();
            $table->text('install_message')->nullable();
            $table->json('resource_snapshot')->nullable();
            $table->longText('console_output')->nullable();
            $table->longText('install_output')->nullable();
            $table->timestamp('last_resource_at')->nullable();
            $table->timestamp('last_console_at')->nullable();
            $table->timestamp('last_install_output_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_runtime_states');
    }
};
