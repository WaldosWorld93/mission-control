<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('from_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('thread_id')->nullable()->constrained('message_threads')->cascadeOnDelete();
            $table->unsignedInteger('sequence_in_thread')->nullable();
            $table->text('content');
            $table->json('mentions')->nullable();
            $table->json('read_by')->nullable();
            $table->string('message_type')->default('chat');
            $table->timestamps();

            $table->index(['team_id', 'project_id']);
            $table->index(['thread_id', 'sequence_in_thread']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
