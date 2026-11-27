<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\InternalMessage;
use App\Models\PlanningYear;
use App\Models\SystemNotification;
use App\Services\AuditService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            if (!$user || !Schema::hasTable('planning_years')) {
                return;
            }

            $allYears = PlanningYear::orderByDesc('year')->get();
            $currentYear = AuditService::getSelectedYear();
            [$globalEntryOpen, $globalEntryMsg] = AuditService::isEntryOpenForDepartment(
                $currentYear->id,
                $user->department_id
            );

            $unreadNotifications = SystemNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            $unreadNotifCount = SystemNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            $unreadMsgCount = InternalMessage::where('is_read', false)
                ->where(function ($q) use ($user) {
                    $q->where('recipient_user_id', $user->id);
                    if ($user->department_id) {
                        $q->orWhere('recipient_department_id', $user->department_id);
                    }
                })
                ->count();

            $pendingReviewCount = $user->isAdmin()
                ? Activity::where('planning_year_id', $currentYear->id)->where('status', 'pending')->count()
                : 0;

            $view->with(compact(
                'allYears',
                'currentYear',
                'globalEntryOpen',
                'globalEntryMsg',
                'unreadNotifications',
                'unreadNotifCount',
                'unreadMsgCount',
                'pendingReviewCount'
            ));
        });
    }
}
