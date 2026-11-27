<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password) {
            if (!$request->routeIs('password.first', 'password.first.update', 'logout')) {
                return redirect()->route('password.first')
                    ->with('warning', 'يرجى تغيير كلمة المرور الافتراضية أولاً قبل البدء في استخدام النظام.');
            }
        }

        return $next($request);
    }
}
