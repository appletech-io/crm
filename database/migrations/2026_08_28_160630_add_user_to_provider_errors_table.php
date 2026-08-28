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
        Schema::table('provider_errors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();

            // Same reasoning as the client_id/candidate_type+candidate_id
            // constraints added alongside this one — MySQL never treats a
            // NULL as colliding with another NULL, so this coexists safely.
            $table->unique(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_errors', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'provider']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
