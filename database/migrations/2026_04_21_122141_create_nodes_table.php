<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->string('fqdn');
            $table->integer('daemon_port')->default(8080);
            $table->text('daemon_token');
            $table->boolean('is_public')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            
            // Stats from heartbeat
            $table->integer('cpu_usage')->nullable();
            $table->integer('memory_total')->nullable();
            $table->integer('memory_used')->nullable();
            $table->integer('disk_total')->nullable();
            $table->integer('disk_used')->nullable();
            
            $table->timestamp('last_heartbeat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
