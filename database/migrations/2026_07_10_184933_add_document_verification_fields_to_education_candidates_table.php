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
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->string('proof_of_address_match')->nullable()->after('dbs_checked_date');
            $table->text('proof_of_address_extracted')->nullable()->after('proof_of_address_match');
            $table->timestamp('proof_of_address_checked_at')->nullable()->after('proof_of_address_extracted');
            $table->string('ni_number_match')->nullable()->after('proof_of_address_checked_at');
            $table->string('ni_number_extracted')->nullable()->after('ni_number_match');
            $table->timestamp('ni_number_checked_at')->nullable()->after('ni_number_extracted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'proof_of_address_match',
                'proof_of_address_extracted',
                'proof_of_address_checked_at',
                'ni_number_match',
                'ni_number_extracted',
                'ni_number_checked_at',
            ]);
        });
    }
};
