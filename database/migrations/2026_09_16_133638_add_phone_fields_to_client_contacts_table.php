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
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->string('direct_dial')->nullable()->after('email');
            $table->string('extension')->nullable()->after('direct_dial');
            $table->string('mobile')->nullable()->after('extension');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->dropColumn(['direct_dial', 'extension', 'mobile']);
        });
    }
};
