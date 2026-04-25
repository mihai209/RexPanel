<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_variables', function (Blueprint $table) {
            $table->id();
            $table->uuid('image_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('env_variable');
            $table->text('default_value')->nullable();
            $table->boolean('user_viewable')->default(true);
            $table->boolean('user_editable')->default(true);
            $table->string('rules')->nullable();
            $table->string('field_type')->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('image_id')->references('id')->on('images')->cascadeOnDelete();
            $table->unique(['image_id', 'env_variable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_variables');
    }
};
