<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('message')->nullable();
            $table->string('severity', 32)->default('warning');
            $table->string('status', 24)->default('open');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('panel_maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('message')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_completed', 'starts_at']);
            $table->index(['ends_at']);
        });

        Schema::create('panel_security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('message')->nullable();
            $table->string('severity', 32)->default('warning');
            $table->string('status', 24)->default('open');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_security_alerts');
        Schema::dropIfExists('panel_maintenance_windows');
        Schema::dropIfExists('panel_incidents');
    }
};
