<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('is_admin');
            $table->unsignedBigInteger('coins')->default(0)->after('is_suspended');
            $table->boolean('two_factor_enabled')->default(false)->after('avatar_provider');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->unsignedInteger('ai_daily_quota_override')->nullable()->after('two_factor_secret');
            $table->string('experimental_view_mode')->nullable()->after('ai_daily_quota_override');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_suspended',
                'coins',
                'two_factor_enabled',
                'two_factor_secret',
                'ai_daily_quota_override',
                'experimental_view_mode',
            ]);
        });
    }
};
