<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_year_id')->constrained('planning_years')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('title', 300);
            $table->string('status', 30)->default('draft')->index(); // draft|submitted|approved|returned
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['planning_year_id', 'department_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_plan_id')->constrained('operational_plans')->cascadeOnDelete();
            $table->foreignId('planning_year_id')->constrained('planning_years')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();

            $table->foreignId('main_objective_id')->constrained('main_objectives')->restrictOnDelete();
            $table->foreignId('sub_objective_id')->constrained('sub_objectives')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();

            $table->string('activity_code', 60)->index();
            $table->unsignedInteger('seq_number')->default(1);

            $table->text('title'); // النشاط
            $table->text('kpi_name'); // مؤشر الأداء
            $table->string('kpi_target', 200); // قيمة المؤشر
            $table->decimal('kpi_target_numeric', 14, 2)->nullable();
            $table->text('verification_means'); // وسائل التحقق
            $table->string('responsible_entity', 250); // الجهة المسئولة
            $table->string('support_type', 20)->default('internal'); // internal|external|both|none
            $table->string('support_internal', 250)->nullable(); // الجهة المساندة داخلي
            $table->string('support_external', 250)->nullable(); // الجهة المساندة خارجي

            $table->boolean('q1')->default(false); // ر1
            $table->boolean('q2')->default(false); // ر2
            $table->boolean('q3')->default(false); // ر3
            $table->boolean('q4')->default(false); // ر4

            $table->string('priority', 20)->default('medium')->index(); // critical|high|medium|low
            $table->decimal('estimated_cost', 14, 2)->default(0); // التكلفة
            $table->string('funding_source', 80)->default('تمويل ذاتي');
            $table->string('budget_item', 150)->nullable();

            $table->string('status', 25)->default('draft')->index(); // draft|pending|approved|rejected
            $table->text('review_note')->nullable(); // سبب الرفض أو ملاحظات الاعتماد
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['planning_year_id', 'department_id', 'status']);
        });

        Schema::create('activity_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('snapshot_json');
            $table->string('change_summary', 300)->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name', 180)->default('النظام');
            $table->timestamps();
            $table->index(['activity_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_versions');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('operational_plans');
    }
};
