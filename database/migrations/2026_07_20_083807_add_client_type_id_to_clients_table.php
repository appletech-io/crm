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
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('client_type_id')->nullable()->after('client_type')->constrained('client_types')->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('client_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_type')->nullable()->after('name');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_type_id');
        });
    }
};
