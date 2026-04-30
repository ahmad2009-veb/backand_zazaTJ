<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class WeWantJson
{
    /**
     * We only accept json
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        $response = $next($request);

        $response->header('Content-Type', 'application/json;charset=UTF-8');
        $response->header('Charset', 'utf-8');

        return $response;
    }
}
