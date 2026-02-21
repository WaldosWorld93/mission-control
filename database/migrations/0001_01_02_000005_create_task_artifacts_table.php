<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('display_name')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path');
            $table->longText('content_text')->nullable();
            $table->string('artifact_type')->default('other');
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('uploaded_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'filename', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_artifacts');
    }
};
