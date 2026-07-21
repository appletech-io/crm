<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('industry_id')->nullable()->after('client_type_id')->constrained()->cascadeOnDelete();
        });

        $this->backfillIndustryId();

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('industry_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('industry_id');
        });
    }

    /**
     * Backfill from the client's client_type where set, otherwise from the
     * single industry attached to its company (best effort where a company
     * operates in more than one sector and no client_type narrows it down).
     */
    private function backfillIndustryId(): void
    {
        DB::table('clients')->orderBy('id')->chunkById(100, function ($clients): void {
            foreach ($clients as $client) {
                $industryId = $client->client_type_id
                    ? DB::table('client_types')->where('id', $client->client_type_id)->value('industry_id')
                    : null;

                $industryId ??= DB::table('company_industry')
                    ->where('company_id', $client->company_id)
                    ->orderBy('industry_id')
                    ->value('industry_id');

                if ($industryId) {
                    DB::table('clients')->where('id', $client->id)->update(['industry_id' => $industryId]);
                }
            }
        });
    }
};
