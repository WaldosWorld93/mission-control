<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('description')->nullable();
            $table->longText('soul_md')->nullable();
            $table->string('soul_hash', 64)->nullable();
            $table->string('status')->default('offline');
            $table->boolean('is_lead')->default(false);
            $table->json('skills')->nullable();
            $table->string('heartbeat_model')->nullable();
            $table->string('work_model')->nullable();
            $table->string('api_token', 64)->unique();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_task_completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('consecutive_errors')->default(0);
            $table->boolean('is_paused')->default(false);
            $table->string('paused_reason')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
