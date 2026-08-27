<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A candidate's compliance value now belongs to a specific field
     * (compliance_item_fields), not the item as a whole, since an item can
     * hold several fields. Dropped and recreated rather than altered in
     * place — simpler and portable across MySQL/SQLite than juggling
     * foreign-key-vs-unique-index drop ordering, and the preceding
     * migration (move_data_type_from_compliance_items_to_fields) already
     * cleared this table's data.
     */
    public function up(): void
    {
        Schema::dropIfExists('candidate_compliance_values');

        Schema::create('candidate_compliance_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_item_field_id')->constrained()->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->date('date_value')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'compliance_item_field_id'], 'candidate_compliance_values_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_compliance_values');

        Schema::create('candidate_compliance_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->constrained()->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->date('date_value')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'compliance_item_id'], 'candidate_compliance_values_unique');
        });
    }
};
