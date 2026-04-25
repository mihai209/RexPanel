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
            $table->string('os')->nullable()->after('cpu_usage');
            $table->string('arch')->nullable()->after('os');
            $table->integer('cpu_total')->default(1)->after('arch');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['os', 'arch', 'cpu_total']);
        });
    }
};
