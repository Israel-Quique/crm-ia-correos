<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocalAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) return $next($request);
        return redirect()->route('login');
    }
}
