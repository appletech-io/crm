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
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->string('confirmation_pdf_path')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_bookings', function (Blueprint $table) {
            $table->dropColumn('confirmation_pdf_path');
        });
    }
};
