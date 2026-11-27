<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('internal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('subject', 250);
            $table->text('body');
            $table->foreignId('parent_message_id')->nullable()->constrained('internal_messages')->nullOnDelete();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 220);
            $table->text('message');
            $table->string('category', 40)->default('system'); // approval|rejection|submission|comment|message|period|risk|system
            $table->string('link_url', 300)->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('risk_obstacles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_year_id')->constrained('planning_years')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('category', 30)->default('administrative'); // financial|technical|administrative|external|legal
            $table->unsignedTinyInteger('likelihood')->default(3); // 1..5
            $table->unsignedTinyInteger('impact')->default(3); // 1..5
            $table->unsignedTinyInteger('risk_score')->default(9);
            $table->string('severity', 20)->default('medium')->index(); // low|medium|high|critical
            $table->text('mitigation_plan')->nullable();
            $table->string('owner_name', 150)->nullable();
            $table->string('status', 25)->default('open')->index(); // open|mitigating|resolved|escalated
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 80)->default('system');
            $table->string('full_name', 180)->default('النظام');
            $table->string('action', 60)->index();
            $table->string('entity_type', 60)->index();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_label', 300)->nullable();
            $table->longText('details_json')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('risk_obstacles');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('internal_messages');
        Schema::dropIfExists('activity_comments');
    }
};
