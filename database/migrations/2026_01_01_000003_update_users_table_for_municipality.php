<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->string('role', 20)->default('department')->index()->after('password');
            $table->foreignId('department_id')->nullable()->after('role')->constrained('departments')->nullOnDelete();
            $table->boolean('must_change_password')->default(true)->after('department_id');
            $table->boolean('is_active')->default(true)->after('must_change_password');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('is_active');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->unsignedBigInteger('deleted_by_id')->nullable()->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'username', 'role', 'must_change_password', 'is_active',
                'failed_login_attempts', 'locked_until', 'last_login_at', 'deleted_by_id'
            ]);
        });
    }
};
