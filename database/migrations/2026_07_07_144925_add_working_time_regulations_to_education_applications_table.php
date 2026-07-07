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
        Schema::table('education_applications', function (Blueprint $table) {
            $table->timestamp('rehabilitation_of_offenders_completed_at')->nullable()->after('security_clearance_accepted_at');
            $table->string('working_time_regulations_opt_out')->nullable()->after('rehabilitation_of_offenders_completed_at');
            $table->timestamp('working_time_regulations_accepted_at')->nullable()->after('working_time_regulations_opt_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_applications', function (Blueprint $table) {
            $table->dropColumn([
                'rehabilitation_of_offenders_completed_at',
                'working_time_regulations_opt_out',
                'working_time_regulations_accepted_at',
            ]);
        });
    }
};
