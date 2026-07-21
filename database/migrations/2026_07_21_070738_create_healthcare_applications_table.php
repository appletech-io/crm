<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('healthcare_applications', function (Blueprint $table) {
            $table->id();
            $table->morphs('candidate');
            $table->string('email')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('status')->default('pending');
            $table->string('token')->unique();
            $table->date('expires_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('cv_parsed_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('healthcare_applications');
    }
};
