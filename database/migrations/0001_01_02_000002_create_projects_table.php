<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->string('color', 7)->nullable();
            $table->foreignUuid('lead_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
        });

        Schema::create('agent_project', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('role_override')->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->unique(['agent_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_project');
        Schema::dropIfExists('projects');
    }
};
