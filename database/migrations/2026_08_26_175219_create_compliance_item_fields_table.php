<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Compliance Item is now a named group of one or more fields — e.g.
     * "DBS" holding a DBS Number (text), Issue Date (date), and Expiry Date
     * (date with expiry tracking) — rather than a single data_type itself.
     */
    public function up(): void
    {
        Schema::create('compliance_item_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('data_type', ['document', 'date', 'date_expiry', 'text']);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_item_fields');
    }
};
