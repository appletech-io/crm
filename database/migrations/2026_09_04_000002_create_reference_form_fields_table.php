<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_form_id')->constrained()->cascadeOnDelete();
            // Stable machine key, auto-generated from the label once and never
            // changed again afterwards — see ReferenceFormField::booted().
            // Referenced both by the answers JSON on candidate_references and
            // by show_when_field_key on a sibling row, so renaming a field's
            // label later must never change it.
            $table->string('key');
            $table->string('label');
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->string('section_heading')->nullable();
            $table->string('show_when_field_key')->nullable();
            $table->string('show_when_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['reference_form_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_form_fields');
    }
};
