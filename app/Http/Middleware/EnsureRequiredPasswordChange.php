<?php

namespace App\Http\Middleware;

use App\Models\Acesso\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRequiredPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs([
            'password.change-required.*',
            'filament.painel.auth.logout',
        ])) {
            return $next($request);
        }

        return redirect()->route('password.change-required.edit');
    }
}
