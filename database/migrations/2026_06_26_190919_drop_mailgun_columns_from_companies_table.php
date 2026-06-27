<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['mailgun_domain', 'mailgun_api_key', 'mailgun_from_email']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('mailgun_domain')->nullable();
            $table->text('mailgun_api_key')->nullable();
            $table->string('mailgun_from_email')->nullable();
        });
    }
};
