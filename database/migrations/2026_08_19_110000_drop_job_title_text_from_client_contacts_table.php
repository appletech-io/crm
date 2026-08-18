<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * job_title_text was only ever a temporary staging column, preserving
     * each contact's job title as free text while client_contact_job_titles
     * was rolled out — every contact that had one has since been mapped to
     * a real ClientContactJobTitle (via the client-contacts:map-job-titles
     * command), so nothing here relies on it any more.
     */
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->dropColumn('job_title_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->string('job_title_text')->nullable()->after('client_contact_job_title_id');
        });
    }
};
