<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CollaborationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\MonthlyFollowupController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\PlanningYearController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Guest Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1')->name('login.post');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Mandatory First-Login Password Change
    Route::get('/password/first-change', [AuthController::class, 'showFirstPasswordChange'])->name('password.first');
    Route::post('/password/first-change', [AuthController::class, 'updateFirstPassword'])->name('password.first.update');

    // Main Protected Application (Requires password changed if first login)
    Route::middleware(['password.changed'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/switch-year', [DashboardController::class, 'switchYear'])->name('year.switch');
        Route::post('/plans/create', [DashboardController::class, 'createPlan'])->name('plans.create');

        // Profile & Password
        Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile.show');
        Route::post('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');

        // Cascading Hierarchy JSON Endpoints
        Route::get('/api/hierarchy/sub-objectives/{mainObjectiveId}', [ObjectiveController::class, 'apiSubObjectives'])->name('api.sub_objectives');
        Route::get('/api/hierarchy/programs/{subObjectiveId}', [ObjectiveController::class, 'apiPrograms'])->name('api.programs');

        // Activities & Operational Plans (All authenticated roles with RBAC)
        Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
        Route::get('/activities/create', [ActivityController::class, 'create'])->name('activities.create');
        Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
        Route::post('/activities/batch', [ActivityController::class, 'batchAction'])->name('activities.batch');
        Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
        Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
        Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
        Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
        Route::post('/activities/{activity}/submit', [ActivityController::class, 'submit'])->name('activities.submit');
        Route::post('/activities/{activity}/versions/{version}/restore', [ActivityController::class, 'restoreVersion'])->name('activities.versions.restore');

        // Attachments & Evidence
        Route::post('/activities/{activity}/attachments', [ActivityController::class, 'uploadAttachment'])->name('attachments.store');
        Route::get('/attachments/{attachment}/download', [ActivityController::class, 'downloadAttachment'])->name('attachments.download');
        Route::delete('/attachments/{attachment}', [ActivityController::class, 'deleteAttachment'])->name('attachments.destroy');

        // Activity Comments
        Route::post('/activities/{activity}/comments', [ActivityController::class, 'storeComment'])->name('comments.store');
        Route::delete('/comments/{comment}', [ActivityController::class, 'deleteComment'])->name('comments.destroy');

        // Advanced Monthly Follow-up
        Route::get('/followups', [MonthlyFollowupController::class, 'index'])->name('followups.index');
        Route::post('/followups', [MonthlyFollowupController::class, 'store'])->name('followups.store');
        Route::delete('/followups/{followup}', [MonthlyFollowupController::class, 'destroy'])->name('followups.destroy');

        // Department Performance Evaluation
        Route::get('/evaluation', [EvaluationController::class, 'index'])->name('evaluation.index');

        // Costs & Budgets (view for all, edit for admin)
        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');

        // Risks & Obstacles
        Route::get('/risks', [RiskController::class, 'index'])->name('risks.index');
        Route::post('/risks', [RiskController::class, 'store'])->name('risks.store');
        Route::put('/risks/{risk}', [RiskController::class, 'update'])->name('risks.update');
        Route::delete('/risks/{risk}', [RiskController::class, 'destroy'])->name('risks.destroy');

        // Internal Notifications & Messaging
        Route::get('/notifications', [CollaborationController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [CollaborationController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [CollaborationController::class, 'markAllNotificationsRead'])->name('notifications.read_all');

        Route::get('/messages', [CollaborationController::class, 'messages'])->name('messages.index');
        Route::post('/messages', [CollaborationController::class, 'sendMessage'])->name('messages.store');
        Route::post('/messages/{message}/read', [CollaborationController::class, 'markMessageRead'])->name('messages.read');
        Route::delete('/messages/{message}', [CollaborationController::class, 'deleteMessage'])->name('messages.destroy');

        // Reports & Exports (Excel & PDF)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/excel/{type}', [ReportController::class, 'exportExcel'])->name('reports.excel');
        Route::get('/reports/export/pdf/{type}', [ReportController::class, 'exportPdf'])->name('reports.pdf');

        // ============================================================
        // GENERAL SUPERVISOR (SUPER ADMIN) ONLY ROUTES
        // ============================================================
        Route::middleware(['admin'])->group(function () {
            // Activity Approval / Rejection / Renumber
            Route::post('/activities/{activity}/approve', [ActivityController::class, 'approve'])->name('activities.approve');
            Route::post('/activities/{activity}/reject', [ActivityController::class, 'reject'])->name('activities.reject');
            Route::post('/activities/renumber', [ActivityController::class, 'renumber'])->name('activities.renumber');

            // Departments Management
            Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            // Users Management
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset_password');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            // Strategic Hierarchy (Main Objectives -> Sub Objectives -> Programs)
            Route::get('/objectives', [ObjectiveController::class, 'index'])->name('objectives.index');
            Route::post('/objectives/main', [ObjectiveController::class, 'storeMain'])->name('objectives.main.store');
            Route::put('/objectives/main/{mainObjective}', [ObjectiveController::class, 'updateMain'])->name('objectives.main.update');
            Route::delete('/objectives/main/{mainObjective}', [ObjectiveController::class, 'destroyMain'])->name('objectives.main.destroy');
            Route::post('/objectives/sub', [ObjectiveController::class, 'storeSub'])->name('objectives.sub.store');
            Route::put('/objectives/sub/{subObjective}', [ObjectiveController::class, 'updateSub'])->name('objectives.sub.update');
            Route::delete('/objectives/sub/{subObjective}', [ObjectiveController::class, 'destroySub'])->name('objectives.sub.destroy');
            Route::post('/objectives/program', [ObjectiveController::class, 'storeProgram'])->name('objectives.program.store');
            Route::put('/objectives/program/{program}', [ObjectiveController::class, 'updateProgram'])->name('objectives.program.update');
            Route::delete('/objectives/program/{program}', [ObjectiveController::class, 'destroyProgram'])->name('objectives.program.destroy');

            // Budgets Allocation (Admin write)
            Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
            Route::delete('/budgets/{budgetAllocation}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

            // Planning Years, Entry Windows & Cloning Previous Plans
            Route::get('/years', [PlanningYearController::class, 'index'])->name('years.index');
            Route::post('/years', [PlanningYearController::class, 'store'])->name('years.store');
            Route::put('/years/{planningYear}', [PlanningYearController::class, 'update'])->name('years.update');
            Route::post('/years/{planningYear}/toggle-entry', [PlanningYearController::class, 'toggleEntry'])->name('years.toggle_entry');
            Route::post('/years/{planningYear}/activate', [PlanningYearController::class, 'setActive'])->name('years.activate');
            Route::post('/years/clone', [PlanningYearController::class, 'clonePlan'])->name('years.clone');
            Route::delete('/years/{planningYear}', [PlanningYearController::class, 'destroy'])->name('years.destroy');

            // Audit Log
            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

            // Recycle Bin (Trash & Restore)
            Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
            Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
            Route::delete('/trash/{type}/{id}/force', [TrashController::class, 'forceDelete'])->name('trash.force_delete');

            // Backup & Restore
            Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups/create', [BackupController::class, 'create'])->name('backups.create');
            Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
            Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
            Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
        });
    });
});
