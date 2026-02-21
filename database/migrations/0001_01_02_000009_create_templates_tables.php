<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('squad_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('use_case')->nullable();
            $table->json('agent_configs')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agent_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('description')->nullable();
            $table->longText('soul_md_template')->nullable();
            $table->boolean('is_lead')->default(false);
            $table->json('default_skills')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_templates');
        Schema::dropIfExists('squad_templates');
    }
};
