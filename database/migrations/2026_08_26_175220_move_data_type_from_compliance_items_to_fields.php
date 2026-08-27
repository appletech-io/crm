<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * data_type now lives per-field (compliance_item_fields), not per-item —
     * an item can hold several differently-typed fields. Existing items
     * (and their candidate values) predate that distinction and are reset
     * rather than migrated, per product decision — this whole feature is
     * still local/demo data at this point.
     */
    public function up(): void
    {
        DB::table('candidate_compliance_values')->delete();
        DB::table('compliance_items')->delete();

        Schema::table('compliance_items', function (Blueprint $table) {
            $table->dropColumn('data_type');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_items', function (Blueprint $table) {
            $table->enum('data_type', ['document', 'date', 'date_expiry', 'text'])->after('name');
        });
    }
};
