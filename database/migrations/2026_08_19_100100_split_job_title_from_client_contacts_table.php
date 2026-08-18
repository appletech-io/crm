<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->string('job_title_text')->nullable()->after('job_title_id');
        });

        // Preserve whatever was previously selected (from the candidate-role
        // job_titles list a contact's own role was wrongly sharing) as free
        // text before the column it came from is dropped below, rather than
        // silently losing it — it can be reconciled against the new,
        // contact-specific list separately.
        DB::statement('
            UPDATE client_contacts
            SET job_title_text = (SELECT name FROM job_titles WHERE job_titles.id = client_contacts.job_title_id)
            WHERE job_title_id IS NOT NULL
        ');

        Schema::table('client_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_title_id');
        });

        Schema::table('client_contacts', function (Blueprint $table) {
            $table->foreignId('client_contact_job_title_id')->nullable()->after('job_title_text')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_contact_job_title_id');
            $table->dropColumn('job_title_text');
        });

        Schema::table('client_contacts', function (Blueprint $table) {
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
