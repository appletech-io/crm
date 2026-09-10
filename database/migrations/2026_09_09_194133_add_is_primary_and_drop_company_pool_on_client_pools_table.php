<?php

use App\Actions\Clients\SyncClientConsultantPool;
use App\Models\Client;
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
        Schema::table('client_pools', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('company_pool');
        });

        Schema::table('client_pools', function (Blueprint $table) {
            $table->dropColumn('company_pool');
        });

        // Backfill: every existing client already assigned to a consultant
        // needs that consultant's main pool created and attached, so no
        // client becomes invisible the moment visibility switches to being
        // pool-based rather than consultant_id-based.
        Client::query()->whereNotNull('consultant_id')->chunkById(200, function ($clients): void {
            foreach ($clients as $client) {
                SyncClientConsultantPool::run($client);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_pools', function (Blueprint $table) {
            $table->dropColumn('is_primary');
            $table->boolean('company_pool')->default(false);
        });
    }
};
