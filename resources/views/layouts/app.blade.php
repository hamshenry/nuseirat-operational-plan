<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة التحكم') — بلدية النصيرات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
</head>
<body>
<div class="app-shell">
    <!-- Right RTL Municipal Sidebar -->
    <aside class="sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo-wrap">
                <img src="{{ asset('images/logo.png') }}" alt="شعار بلدية النصيرات">
            </div>
            <h1 class="sidebar-title">بلدية النصيرات</h1>
            <p class="sidebar-subtitle">نظام إدارة الخطة التشغيلية</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">لوحة المتابعة والخطط</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span>🏠</span><span>الرئيسية ولوحة التحكم</span>
            </a>
            <a href="{{ route('activities.index') }}" class="nav-link {{ request()->routeIs('activities.index', 'activities.show', 'activities.edit') ? 'active' : '' }}">
                <span>📋</span><span>الخطط والأنشطة</span>
                @if(($pendingReviewCount ?? 0) > 0)
                    <span class="nav-badge nav-badge-amber" title="أنشطة بانتظار الاعتماد">{{ $pendingReviewCount }}</span>
                @endif
            </a>
            <a href="{{ route('activities.create') }}" class="nav-link {{ request()->routeIs('activities.create') ? 'active' : '' }}">
                <span>➕</span><span>إدخال نشاط جديد</span>
            </a>

            <div class="nav-section-label">المتابعة والتقييم والموازنة</div>
            <a href="{{ route('followups.index') }}" class="nav-link {{ request()->routeIs('followups.*') ? 'active' : '' }}">
                <span>📅</span><span>المتابعة الشهرية المتقدمة</span>
            </a>
            <a href="{{ route('evaluation.index') }}" class="nav-link {{ request()->routeIs('evaluation.*') ? 'active' : '' }}">
                <span>🏆</span><span>تقييم أداء الدوائر</span>
            </a>
            <a href="{{ route('budgets.index') }}" class="nav-link {{ request()->routeIs('budgets.*') ? 'active' : '' }}">
                <span>💰</span><span>التكاليف والموازنات</span>
            </a>
            <a href="{{ route('risks.index') }}" class="nav-link {{ request()->routeIs('risks.*') ? 'active' : '' }}">
                <span>⚠️</span><span>المخاطر والمعوقات</span>
            </a>
            <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <span>📊</span><span>التقارير والتصدير (Excel / PDF)</span>
            </a>

            <div class="nav-section-label">التواصل والإشعارات</div>
            <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                <span>🔔</span><span>الإشعارات الداخلية</span>
                @if(($unreadNotifCount ?? 0) > 0)
                    <span class="nav-badge">{{ $unreadNotifCount }}</span>
                @endif
            </a>
            <a href="{{ route('messages.index') }}" class="nav-link {{ request()->routeIs('messages.*') ? 'active' : '' }}">
                <span>✉️</span><span>المراسلات الداخلية</span>
                @if(($unreadMsgCount ?? 0) > 0)
                    <span class="nav-badge">{{ $unreadMsgCount }}</span>
                @endif
            </a>

            @if(auth()->user()->isAdmin())
                <div class="nav-section-label">إدارة المشرف العام</div>
                <a href="{{ route('departments.index') }}" class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                    <span>🏛️</span><span>الدوائر البلدية</span>
                </a>
                <a href="{{ route('objectives.index') }}" class="nav-link {{ request()->routeIs('objectives.*') ? 'active' : '' }}">
                    <span>🎯</span><span>الأهداف والبرامج</span>
                </a>
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <span>👥</span><span>المستخدمون والصلاحيات</span>
                </a>
                <a href="{{ route('years.index') }}" class="nav-link {{ request()->routeIs('years.*') ? 'active' : '' }}">
                    <span>📆</span><span>السنوات وفترات الإدخال</span>
                </a>
                <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                    <span>📜</span><span>السجل ومقارنة الإصدارات</span>
                </a>
                <a href="{{ route('trash.index') }}" class="nav-link {{ request()->routeIs('trash.*') ? 'active' : '' }}">
                    <span>🗑️</span><span>سلة المحذوفات والاستعادة</span>
                </a>
                <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}">
                    <span>💾</span><span>النسخ الاحتياطي والاستعادة</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-footer">
            بلدية النصيرات &copy; {{ date('Y') }}
        </div>
    </aside>

    <!-- Main Workspace -->
    <div class="main-area">
        <header class="topbar">
            <div class="topbar-right">
                <button type="button" class="menu-toggle" onclick="toggleMobileSidebar()" aria-label="القائمة">☰</button>

                @if(isset($allYears) && $allYears->count())
                    <form method="POST" action="{{ route('year.switch') }}" class="year-selector">
                        @csrf
                        <span style="font-size:12px;color:var(--text-soft);font-weight:700;">السنة التخطيطية:</span>
                        <select name="year_id" onchange="this.form.submit()">
                            @foreach($allYears as $y)
                                <option value="{{ $y->id }}" @selected(isset($currentYear) && $currentYear->id === $y->id)>
                                    {{ $y->year }} {{ $y->is_active ? '(النشطة)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if(isset($globalEntryOpen))
                    <span class="period-pill {{ $globalEntryOpen ? 'open' : 'closed' }}" title="{{ $globalEntryMsg ?? '' }}">
                        <span class="period-dot"></span>
                        {{ $globalEntryOpen ? 'فترة الإدخال مفتوحة' : 'فترة الإدخال مغلقة' }}
                    </span>
                @endif
            </div>

            <div class="topbar-left">
                <!-- Notifications Bell -->
                <div class="notif-wrapper">
                    <button type="button" class="icon-btn" onclick="toggleNotifications()" title="الإشعارات">
                        🔔
                        @if(($unreadNotifCount ?? 0) > 0)
                            <span class="icon-btn-badge">{{ $unreadNotifCount }}</span>
                        @endif
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span>الإشعارات غير المقروءة ({{ $unreadNotifCount ?? 0 }})</span>
                            <a href="{{ route('notifications.index') }}" style="font-size:12px;">عرض الكل</a>
                        </div>
                        @forelse(($unreadNotifications ?? []) as $n)
                            <form method="POST" action="{{ route('notifications.read', $n) }}">
                                @csrf
                                <button type="submit" class="notif-item" style="width:100%;text-align:right;background:none;border:none;border-bottom:1px solid var(--border);cursor:pointer;font-family:inherit;">
                                    <div class="notif-item-title">{{ $n->title }}</div>
                                    <div class="notif-item-msg">{{ $n->message }}</div>
                                </button>
                            </form>
                        @empty
                            <div style="padding:18px;text-align:center;color:var(--text-soft);font-size:13px;">
                                لا توجد إشعارات جديدة حالياً.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- User Info -->
                <a href="{{ route('profile.show') }}" class="user-chip" title="الملف الشخصي وتغيير كلمة المرور">
                    <div class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                    <div class="user-meta">
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role">
                            {{ auth()->user()->isAdmin() ? 'المشرف العام' : (auth()->user()->department?->name ?? 'دائرة') }}
                        </div>
                    </div>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">خروج</button>
                </form>
            </div>
        </header>

        <main class="content-wrap">
            @if(session('success'))
                <div class="alert alert-success">
                    <span>✅ {{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">
                    <span>⛔ {{ session('error') }}</span>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">
                    <span>⚠️ {{ session('warning') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" style="flex-direction:column;align-items:flex-start;">
                    <strong>يرجى مراجعة الأخطاء التالية:</strong>
                    <ul style="margin:6px 0 0;padding-right:18px;">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
