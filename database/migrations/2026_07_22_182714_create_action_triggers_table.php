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
        Schema::create('action_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained()->cascadeOnDelete();
            $table->morphs('model');
            $table->foreignId('todo_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['action_id', 'model_type', 'model_id'], 'action_triggers_action_model_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_triggers');
    }
};
