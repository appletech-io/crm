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
        Schema::table('action_triggers', function (Blueprint $table) {
            // The unique index being dropped below is also what covers the
            // action_id foreign key on MySQL, which refuses to drop it
            // without a replacement index already in place.
            $table->index('action_id', 'action_triggers_action_id_index');
            $table->dropUnique('action_triggers_action_model_unique');
            $table->timestamp('resolved_at')->nullable()->after('todo_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_triggers', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
            $table->unique(['action_id', 'model_type', 'model_id'], 'action_triggers_action_model_unique');
            $table->dropIndex('action_triggers_action_id_index');
        });
    }
};
