<?php

namespace App\Http\Middleware;

use App\CentralLogics\Helpers;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiModulePermissionMiddleware
{


    public function handle(Request $request, Closure $next, $module)
    {
        if (Auth::guard('admin-api')->check() && Helpers::api_module_permission_check($module)) {
            return $next($request);
        }

        return response()->json([
            'message' => trans('messages.access_denied')
        ], 403); // 403 Forbidden status code
    }
}
