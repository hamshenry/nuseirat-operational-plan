<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('title', 200);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_entry_open')->default(true);
            $table->date('entry_start_date')->nullable();
            $table->date('entry_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_years');
    }
};
