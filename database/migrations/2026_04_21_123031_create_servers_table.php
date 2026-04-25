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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->foreignId('node_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Resources
            $table->integer('cpu')->default(100); // 100 = 1 core
            $table->integer('memory')->default(1024); // MB
            $table->integer('disk')->default(5120); // MB
            $table->integer('swap')->default(0); // MB
            $table->integer('io')->default(500); // Weight 10-1000
            
            $table->string('status')->default('offline');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
