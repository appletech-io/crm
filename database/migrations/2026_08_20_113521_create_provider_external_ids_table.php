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
        Schema::create('provider_external_ids', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('provider');
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'provider'], 'provider_external_ids_model_provider_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_external_ids');
    }
};
