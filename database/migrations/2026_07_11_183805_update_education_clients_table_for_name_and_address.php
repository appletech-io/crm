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
        Schema::table('education_clients', function (Blueprint $table) {
            $table->dropColumn(['email', 'subject', 'grade_level', 'notes']);
        });

        Schema::table('education_clients', function (Blueprint $table) {
            $table->string('client_type')->nullable()->after('name');
            $table->text('address')->nullable()->after('client_type');
            $table->string('city')->nullable()->after('address');
            $table->string('postcode')->nullable()->after('city');
            $table->string('county')->nullable()->after('postcode');
            $table->string('phone')->nullable()->after('county');
            $table->string('extension')->nullable()->after('phone');
            $table->string('fax')->nullable()->after('extension');
            $table->string('website')->nullable()->after('fax');
            $table->text('notes')->nullable()->after('website');
            $table->json('key_stages')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_clients', function (Blueprint $table) {
            $table->dropColumn([
                'client_type', 'address', 'city', 'postcode', 'county',
                'phone', 'extension', 'fax', 'website', 'notes', 'key_stages',
            ]);
        });

        Schema::table('education_clients', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            $table->string('grade_level')->nullable();
            $table->text('notes')->nullable();
        });
    }
};
