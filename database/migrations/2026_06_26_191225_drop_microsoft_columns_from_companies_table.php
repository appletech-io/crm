<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ms_tenant_id', 'ms_client_id', 'ms_client_secret', 'ms_sender_email']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('ms_tenant_id')->nullable();
            $table->string('ms_client_id')->nullable();
            $table->text('ms_client_secret')->nullable();
            $table->string('ms_sender_email')->nullable();
        });
    }
};
