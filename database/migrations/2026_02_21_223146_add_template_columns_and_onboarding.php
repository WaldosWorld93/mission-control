<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_templates', function (Blueprint $table) {
            $table->string('heartbeat_model')->nullable()->after('is_lead');
            $table->string('work_model')->nullable()->after('heartbeat_model');
            $table->string('skill_profile')->nullable()->after('work_model');
            $table->unsignedInteger('heartbeat_interval_seconds')->nullable()->after('skill_profile');
        });

        Schema::table('squad_templates', function (Blueprint $table) {
            $table->decimal('estimated_daily_cost', 8, 2)->nullable()->after('is_public');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('default_work_model');
        });
    }

    public function down(): void
    {
        Schema::table('agent_templates', function (Blueprint $table) {
            $table->dropColumn(['heartbeat_model', 'work_model', 'skill_profile', 'heartbeat_interval_seconds']);
        });

        Schema::table('squad_templates', function (Blueprint $table) {
            $table->dropColumn('estimated_daily_cost');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
