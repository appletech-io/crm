<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE candidate_documents MODIFY document_type ENUM('cv', 'photo', 'safeguarding_training', 'proof_of_address', 'proof_of_address_2', 'proof_of_ni', 'birth_certificate', 'passport', 'dbs_front', 'dbs_back', 'uk_naric', 'professional_registration', 'reference', 'other', 'qualification', 'benedicts_law') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE candidate_documents MODIFY document_type ENUM('cv', 'photo', 'safeguarding_training', 'proof_of_address', 'proof_of_address_2', 'proof_of_ni', 'birth_certificate', 'passport', 'dbs_front', 'dbs_back', 'uk_naric', 'professional_registration', 'reference', 'other', 'qualification') NOT NULL");
        }
    }
};
