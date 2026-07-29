<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_references', function (Blueprint $table) {
            $table->string('token')->nullable()->unique()->after('status');
            $table->date('expires_on')->nullable()->after('token');
            $table->json('answers')->nullable()->after('expires_on');
            $table->timestamp('submitted_at')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_references', function (Blueprint $table) {
            $table->dropColumn(['token', 'expires_on', 'answers', 'submitted_at']);
        });
    }
};
