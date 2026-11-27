<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('main_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_year_id')->constrained('planning_years')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('title', 400);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['planning_year_id', 'sort_order']);
        });

        Schema::create('sub_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_objective_id')->constrained('main_objectives')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('title', 400);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['main_objective_id', 'sort_order']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_objective_id')->constrained('sub_objectives')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('title', 400);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sub_objective_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
        Schema::dropIfExists('sub_objectives');
        Schema::dropIfExists('main_objectives');
    }
};
