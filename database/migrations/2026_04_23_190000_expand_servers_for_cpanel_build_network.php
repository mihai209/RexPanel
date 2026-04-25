<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('external_id')->nullable()->after('description');
            $table->string('threads')->nullable()->after('io');
            $table->boolean('oom_disabled')->default(false)->after('threads');
            $table->integer('database_limit')->nullable()->after('oom_disabled');
            $table->integer('allocation_limit')->nullable()->after('database_limit');
            $table->integer('backup_limit')->nullable()->after('allocation_limit');
        });

        Schema::table('node_allocations', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('server_id');
        });
    }

    public function down(): void
    {
        Schema::table('node_allocations', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'external_id',
                'threads',
                'oom_disabled',
                'database_limit',
                'allocation_limit',
                'backup_limit',
            ]);
        });
    }
};
