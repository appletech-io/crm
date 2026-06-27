<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('email_provider')->default('microsoft')->after('name');
            $table->string('mailgun_domain')->nullable()->after('ms_sender_email');
            $table->text('mailgun_api_key')->nullable()->after('mailgun_domain');
            $table->string('mailgun_from_email')->nullable()->after('mailgun_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['email_provider', 'mailgun_domain', 'mailgun_api_key', 'mailgun_from_email']);
        });
    }
};
