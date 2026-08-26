<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A temp vacancy can now be raised for general cover with no
        // specific client, so the industry can no longer be inferred via
        // whereHas('client', ...) — it needs its own column, the same way
        // Client/JobTitle/JobStatus already carry one.
        Schema::table('vacancies', function (Blueprint $table): void {
            $table->foreignId('industry_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->change();
        });

        $this->backfillIndustryId();

        Schema::table('vacancies', function (Blueprint $table): void {
            $table->foreignId('industry_id')->nullable(false)->change();
        });
    }

    /**
     * Every existing vacancy currently has a client, so its industry is
     * simply the one that client belongs to. Done as a plain per-row update
     * (rather than a single joined UPDATE) since a joined UPDATE isn't
     * portable across the MySQL/SQLite drivers this app runs migrations on.
     */
    private function backfillIndustryId(): void
    {
        DB::table('vacancies')->orderBy('id')->chunkById(100, function ($vacancies): void {
            foreach ($vacancies as $vacancy) {
                $industryId = DB::table('clients')->where('id', $vacancy->client_id)->value('industry_id');

                if ($industryId) {
                    DB::table('vacancies')->where('id', $vacancy->id)->update(['industry_id' => $industryId]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('industry_id');
        });
    }
};
