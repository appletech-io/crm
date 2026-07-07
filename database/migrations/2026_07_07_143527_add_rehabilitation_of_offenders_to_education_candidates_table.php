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
            $table->string('unspent_convictions')->nullable()->after('overseas_details');
            $table->text('unspent_convictions_details')->nullable()->after('unspent_convictions');
            $table->string('spent_convictions_not_protected')->nullable()->after('unspent_convictions_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn(['unspent_convictions', 'unspent_convictions_details', 'spent_convictions_not_protected']);
        });
    }
};
