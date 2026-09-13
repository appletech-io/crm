<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('invoiceable_type');
            $table->unsignedBigInteger('invoiceable_id');
            $table->string('number')->nullable()->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('vat_amount');
            $table->unsignedInteger('total');
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['invoiceable_type', 'invoiceable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
