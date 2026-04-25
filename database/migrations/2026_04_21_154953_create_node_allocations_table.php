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
        Schema::create('node_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->onDelete('cascade');
            $table->string('ip');
            $table->string('ip_alias')->nullable();
            $table->integer('port');
            $table->foreignId('server_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->unique(['node_id', 'ip', 'port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_allocations');
    }
};
