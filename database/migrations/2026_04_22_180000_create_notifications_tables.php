<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('message');
            $table->string('severity', 32)->default('info');
            $table->string('category', 40)->default('general');
            $table->string('link_url', 512)->nullable();
            $table->string('source_type', 64)->default('admin_manual');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('browser_eligible')->default(false);
            $table->boolean('email_eligible')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32);
            $table->string('status', 24);
            $table->string('target', 512)->nullable();
            $table->string('template_key', 64)->nullable();
            $table->string('event_key', 64)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_text')->nullable();
            $table->foreignId('attempted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retried_from_id')->nullable()->constrained('notification_delivery_logs')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['created_at']);
        });

        Schema::create('user_browser_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 1024);
            $table->json('keys')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_browser_subscriptions');
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('user_notifications');
    }
};
