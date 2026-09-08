<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every Ask Assistant tool scopes its data to the user's active industry,
     * so a stored transcript only makes sense within the industry it was
     * recorded under. These columns record that scope, letting a conversation
     * be resumed only where it belongs — and letting the agent be identified
     * so a second conversational agent never picks up this one's history.
     *
     * Existing rows keep a null industry_id and agent, which no scoped query
     * matches, so they are never resumed.
     */
    public function up(): void
    {
        Schema::table($this->conversationsTable(), function (Blueprint $table) {
            $table->foreignId('industry_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->string('agent')->nullable()->after('industry_id');
            $table->timestamp('dismissed_at')->nullable()->after('title');

            $table->index(
                ['user_id', 'industry_id', 'agent', 'dismissed_at', 'updated_at'],
                'agent_conversations_resume_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table($this->conversationsTable(), function (Blueprint $table) {
            $table->dropIndex('agent_conversations_resume_index');
            $table->dropConstrainedForeignId('industry_id');
            $table->dropColumn(['agent', 'dismissed_at']);
        });
    }

    /**
     * Resolve the table name the way laravel/ai does, so this keeps matching
     * the package's own migration if that config value is ever set.
     */
    private function conversationsTable(): string
    {
        return config('ai.conversations.tables.conversations', 'agent_conversations');
    }
};
