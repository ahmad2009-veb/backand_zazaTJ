<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;

class VendorTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (strlen($token) < 1) {
            return response()->json([
                'errors' => [
                    ['code' => 'auth-001', 'message' => 'Unauthorized.'],
                ],
            ], 401);
        }
        $vendor = Vendor::where('auth_token', $token)->first();
        if ($vendor) {
            $request['vendor'] = $vendor;

            return $next($request);
        }

        return response()->json([
            'errors' => [
                ['code' => 'auth-001', 'message' => 'Unauthorized.'],
            ],
        ], 401);
    }
}
