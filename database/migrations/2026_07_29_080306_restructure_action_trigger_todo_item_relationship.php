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
        Schema::table('todo_items', function (Blueprint $table) {
            $table->foreignId('action_trigger_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('action_triggers', function (Blueprint $table) {
            $table->dropForeign(['todo_item_id']);
            $table->dropColumn('todo_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_triggers', function (Blueprint $table) {
            $table->foreignId('todo_item_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('todo_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('action_trigger_id');
        });
    }
};
