<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        if (Auth::user()->role !== 'admin') {
            Auth::logout();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'No tienes permisos para acceder al panel de administración.']);
        }

        return $next($request);
    }
}
