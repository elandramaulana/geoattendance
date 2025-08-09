<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEmployeeActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && $user->employee && !$user->employee->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive. Please contact administrator.'
            ], 403);
        }

        return $next($request);
    }
}