<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('route_id', 16)->nullable()->after('uuid')->unique();
        });

        DB::table('servers')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $server): void {
                do {
                    $candidate = Str::lower(Str::random(8));
                } while (DB::table('servers')->where('route_id', $candidate)->exists());

                DB::table('servers')
                    ->where('id', $server->id)
                    ->update(['route_id' => $candidate]);
            });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropUnique(['route_id']);
            $table->dropColumn('route_id');
        });
    }
};
