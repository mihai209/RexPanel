<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->integer('coins_delta')->default(0);
            $table->integer('wallet_before')->default(0);
            $table->integer('wallet_after')->default(0);
            $table->json('resource_delta')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'event_type']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
    }
};
