<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->text('result')->nullable();
            $table->text('error_message')->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attempts');
    }
};
