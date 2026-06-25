<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE candidate_activities MODIFY COLUMN type ENUM('email', 'note', 'call', 'meeting', 'other', 'status_automation') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE candidate_activities MODIFY COLUMN type ENUM('email', 'note', 'call', 'meeting', 'other') NOT NULL");
    }
};
