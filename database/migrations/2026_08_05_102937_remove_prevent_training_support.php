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
        // Recategorise any already-uploaded Prevent Training certificates as
        // "Other" before the enum value they reference is dropped, so the
        // underlying files/rows aren't orphaned.
        DB::table('candidate_documents')
            ->where('document_type', 'prevent_training')
            ->update(['document_type' => 'other']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE candidate_documents MODIFY document_type ENUM('cv', 'photo', 'safeguarding_training', 'proof_of_address', 'proof_of_address_2', 'proof_of_ni', 'birth_certificate', 'passport', 'dbs_front', 'dbs_back', 'uk_naric', 'professional_registration', 'reference', 'other', 'qualification') NOT NULL");
        }

        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropColumn('prevent_training_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE candidate_documents MODIFY document_type ENUM('cv', 'photo', 'prevent_training', 'safeguarding_training', 'proof_of_address', 'proof_of_address_2', 'proof_of_ni', 'birth_certificate', 'passport', 'dbs_front', 'dbs_back', 'uk_naric', 'professional_registration', 'reference', 'other', 'qualification') NOT NULL");
        }

        Schema::table('education_candidates', function (Blueprint $table) {
            $table->string('prevent_training_completed')->nullable()->after('safeguarding_certified_date');
        });
    }
};
