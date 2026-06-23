<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->string('title')->nullable()->after('id');
            $table->string('first_name')->nullable()->after('title');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('previous_surname')->nullable()->after('last_name');
            $table->string('gender')->nullable()->after('previous_surname');
            $table->string('nationality')->nullable()->after('gender');
            $table->date('date_of_birth')->nullable()->after('nationality');
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('place_of_birth');
            $table->string('postcode')->nullable()->after('address');
            $table->string('city')->nullable()->after('postcode');
            $table->string('county')->nullable()->after('city');
            $table->string('country')->nullable()->after('county');
            $table->string('mobile')->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('mobile');
            $table->string('emergency_contact_number')->nullable()->after('emergency_contact_name');
            $table->foreignId('consultant_id')->nullable()->after('emergency_contact_number')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('education_candidates', function (Blueprint $table) {
            $table->dropForeign(['consultant_id']);
            $table->dropColumn([
                'title',
                'first_name',
                'middle_name',
                'last_name',
                'previous_surname',
                'gender',
                'nationality',
                'date_of_birth',
                'place_of_birth',
                'address',
                'postcode',
                'city',
                'county',
                'country',
                'phone',
                'mobile',
                'emergency_contact_name',
                'emergency_contact_number',
                'consultant_id',
            ]);
        });
    }
};
