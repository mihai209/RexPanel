<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('type')->nullable()->after('action');
            $table->json('metadata')->nullable()->after('type');
        });

        Schema::create('account_reward_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('selected_period')->default('minute');
            $table->unsignedInteger('daily_streak')->default(0);
            $table->boolean('reward_accrual_disabled')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_daily_claim_at')->nullable();
            $table->timestamp('minute_claimed_at')->nullable();
            $table->timestamp('hour_claimed_at')->nullable();
            $table->timestamp('day_claimed_at')->nullable();
            $table->timestamp('week_claimed_at')->nullable();
            $table->timestamp('month_claimed_at')->nullable();
            $table->timestamp('year_claimed_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('account_afk_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_timer_reward_at')->nullable();
            $table->timestamp('next_payout_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('policy_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('policy_key');
            $table->string('severity')->default('info');
            $table->integer('score_delta')->default(0);
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['policy_key', 'created_at']);
        });

        Schema::create('remediation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_event_id')->nullable()->constrained('policy_events')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action_type');
            $table->string('status')->default('pending');
            $table->timestamp('cooldown_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'action_type']);
        });

        Schema::create('abuse_score_windows', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->integer('score')->default(0);
            $table->timestamp('window_started_at');
            $table->timestamp('window_ends_at');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'window_ends_at']);
        });

        Schema::create('service_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('checked_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['node_id', 'checked_at']);
            $table->index(['server_id', 'checked_at']);
        });

        Schema::create('anti_miner_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('cpu_percent');
            $table->timestamp('sampled_at');
            $table->integer('resulting_score_delta')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'sampled_at']);
            $table->index(['node_id', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anti_miner_samples');
        Schema::dropIfExists('service_health_checks');
        Schema::dropIfExists('abuse_score_windows');
        Schema::dropIfExists('remediation_actions');
        Schema::dropIfExists('policy_events');
        Schema::dropIfExists('account_afk_states');
        Schema::dropIfExists('account_reward_states');

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['type', 'metadata']);
        });
    }
};
