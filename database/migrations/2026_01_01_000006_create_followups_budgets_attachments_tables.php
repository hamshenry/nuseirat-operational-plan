<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('planning_year_id')->constrained('planning_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('month')->index(); // 1..12
            $table->decimal('planned_percent', 5, 2)->default(0);
            $table->decimal('actual_percent', 5, 2)->default(0);
            $table->string('achieved_value', 200)->nullable();
            $table->decimal('actual_cost', 14, 2)->default(0);
            $table->string('execution_status', 30)->default('not_started'); // not_started|in_progress|completed|delayed|blocked
            $table->text('achievements_note')->nullable();
            $table->text('obstacles_note')->nullable();
            $table->text('next_steps')->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['activity_id', 'month']);
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_year_id')->constrained('planning_years')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->decimal('allocated_amount', 14, 2)->default(0);
            $table->decimal('self_funded', 14, 2)->default(0);
            $table->decimal('grants_funded', 14, 2)->default(0);
            $table->decimal('external_funded', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['planning_year_id', 'department_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('monthly_followup_id')->nullable()->constrained('monthly_followups')->nullOnDelete();
            $table->string('title', 250);
            $table->string('original_filename', 255);
            $table->string('stored_path', 350);
            $table->string('mime_type', 120)->default('application/octet-stream');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('monthly_followups');
    }
};
