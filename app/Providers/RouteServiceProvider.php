<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));


            Route::prefix('vendor-panel')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/vendor.php'));

            Route::prefix('api/v1')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v1/api.php'));

            Route::prefix('api/v3')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v3/api.php'));

            Route::prefix('api/v3')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v3/admin.php'));

            Route::prefix('api/v3')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api/v3/vendor.php'));

            // Backward-compat bridge: if an endpoint is not implemented in v3,
            // proxy it to the equivalent v1 path (same HTTP method/payload).
            Route::prefix('api/v3')
                ->middleware('api')
                ->any('{path}', function (Request $request, string $path) {
                    $queryString = $request->getQueryString();
                    $forwardPath = '/api/v1/' . $path . ($queryString ? ('?' . $queryString) : '');

                    $forwardRequest = Request::create(
                        $forwardPath,
                        $request->method(),
                        $request->all(),
                        $request->cookies->all(),
                        $request->files->all(),
                        $request->server->all(),
                        $request->getContent()
                    );

                    $forwardRequest->headers->replace($request->headers->all());

                    $response = app()->handle($forwardRequest);

                    if ($response->getStatusCode() === 404) {
                        return response()->json([
                            'message' => 'Route not found in api/v3 or api/v1',
                        ], 404);
                    }

                    $response->headers->set('X-API-Version-Compat', 'v1-via-v3');

                    return $response;
                })
                ->where('path', '.*');
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
