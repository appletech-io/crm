<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE candidate_documents MODIFY document_type ENUM('cv', 'photo', 'prevent_training', 'safeguarding_training', 'proof_of_address', 'proof_of_address_2', 'proof_of_ni', 'birth_certificate', 'passport', 'dbs_front', 'dbs_back', 'uk_naric', 'professional_registration') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE candidate_documents MODIFY document_type ENUM('cv', 'photo', 'prevent_training', 'safeguarding_training', 'proof_of_address', 'proof_of_address_2', 'proof_of_ni', 'birth_certificate', 'passport', 'dbs_front', 'dbs_back', 'uk_naric') NOT NULL");
    }
};
