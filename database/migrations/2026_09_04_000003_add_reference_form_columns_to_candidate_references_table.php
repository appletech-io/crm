<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_references', function (Blueprint $table) {
            $table->foreignId('reference_form_id')->nullable()->after('type')->constrained()->nullOnDelete();
            // Snapshot of the reference form's sections/fields at the moment
            // this reference was created — see ReferenceFormRenderer. Null
            // on every row created before this migration, which keeps
            // rendering/validating against the legacy ReferenceFormSchema
            // class via the type column instead.
            $table->json('schema')->nullable()->after('reference_form_id');
            $table->string('type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_references', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_form_id');
            $table->dropColumn('schema');
            // Historical rows always have type set, so reverting to
            // non-nullable is safe for pre-existing data — only rows created
            // after this migration (which may have relied on the dynamic
            // reference_form_id instead) could violate it.
            $table->string('type')->nullable(false)->change();
        });
    }
};
