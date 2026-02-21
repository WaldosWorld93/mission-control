<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('status_reported');
            $table->string('soul_hash_reported', 64)->nullable();
            $table->foreignUuid('current_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
    }
};
