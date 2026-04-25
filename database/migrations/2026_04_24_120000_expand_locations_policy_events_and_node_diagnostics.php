<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->string('image_url')->nullable()->after('description');
        });

        DB::table('locations')->update([
            'short_name' => DB::raw('name'),
        ]);

        Schema::table('locations', function (Blueprint $table) {
            $table->unique('short_name');
        });

        Schema::table('policy_events', function (Blueprint $table) {
            $table->string('title')->nullable()->after('reason');
            $table->string('status')->default('open')->after('title');
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->index(['status', 'created_at']);
        });

        DB::table('policy_events')
            ->whereNull('status')
            ->update(['status' => 'open']);

        Schema::table('nodes', function (Blueprint $table) {
            $table->json('connector_diagnostics')->nullable()->after('last_heartbeat');
            $table->timestamp('diagnostics_updated_at')->nullable()->after('connector_diagnostics');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['connector_diagnostics', 'diagnostics_updated_at']);
        });

        Schema::table('policy_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['title', 'status', 'resolved_at']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['short_name']);
            $table->dropColumn(['short_name', 'image_url']);
        });
    }
};
