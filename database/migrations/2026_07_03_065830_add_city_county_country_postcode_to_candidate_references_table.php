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
        Schema::table('candidate_references', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
            $table->string('county')->nullable()->after('city');
            $table->string('country')->nullable()->after('county');
            $table->string('postcode')->nullable()->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_references', function (Blueprint $table) {
            $table->dropColumn(['city', 'county', 'country', 'postcode']);
        });
    }
};
