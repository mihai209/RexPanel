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
        Schema::table('nodes', function (Blueprint $table) {
            $table->integer('memory_limit')->default(8192)->after('maintenance_mode'); // MB
            $table->integer('memory_overallocate')->default(0)->after('memory_limit'); // %
            $table->integer('disk_limit')->default(51200)->after('memory_overallocate'); // MB
            $table->integer('disk_overallocate')->default(0)->after('disk_limit'); // %
            $table->string('daemon_base')->default('/var/lib/ra-panel')->after('disk_overallocate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['memory_limit', 'memory_overallocate', 'disk_limit', 'disk_overallocate', 'daemon_base']);
        });
    }
};
