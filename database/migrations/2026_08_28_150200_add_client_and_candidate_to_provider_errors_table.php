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
            $table->foreignId('booking_id')->nullable()->change();
            $table->foreignId('client_id')->nullable()->after('booking_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('candidate');

            // MySQL treats a NULL in a unique index as never colliding with
            // another NULL, so these three constraints coexist safely on one
            // table: a booking-error row has null client/candidate columns
            // (and vice versa), so only the relevant constraint ever applies.
            $table->unique(['client_id', 'provider']);
            $table->unique(['candidate_type', 'candidate_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_errors', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'provider']);
            $table->dropUnique(['candidate_type', 'candidate_id', 'provider']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropMorphs('candidate');
            $table->foreignId('booking_id')->nullable(false)->change();
        });
    }
};
