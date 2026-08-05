<?php

use App\Models\Vacancy;
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
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->boolean('open_for_applications')->default(true)->after('positions_available');
        });

        Vacancy::withTrashed()->whereNull('slug')->each(function (Vacancy $vacancy): void {
            $vacancy->update(['slug' => Vacancy::generateUniqueSlug($vacancy->title)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropColumn(['slug', 'open_for_applications']);
        });
    }
};
