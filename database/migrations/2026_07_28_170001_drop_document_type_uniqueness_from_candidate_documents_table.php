<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * References allow more than one document per candidate, so a candidate can no
     * longer have at most one row per document_type. The application still enforces
     * single-document semantics for every other type when reading/writing.
     */
    public function up(): void
    {
        Schema::table('candidate_documents', function (Blueprint $table) {
            $table->dropUnique('candidate_documents_candidate_document_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_documents', function (Blueprint $table) {
            $table->unique(['candidate_type', 'candidate_id', 'document_type'], 'candidate_documents_candidate_document_type_unique');
        });
    }
};
