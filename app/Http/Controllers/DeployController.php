<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class DeployController extends Controller
{
    /**
     * Runs post-deploy tasks (migrations, cache warming) over a plain HTTP
     * request instead of SSH, since non-interactive SSH command execution
     * is unreliable on this shared hosting account while normal web
     * requests are not. vendor/ ships pre-built from CI, so this route
     * never needs to run composer itself.
     */
    public function run(Request $request): JsonResponse
    {
        $token = (string) config('services.deploy.token');

        if ($token === '' || ! hash_equals($token, (string) $request->input('token'))) {
            abort(403);
        }

        set_time_limit(120);

        $steps = [];

        Artisan::call('storage:link');
        $steps['storage_link'] = trim(Artisan::output());

        Artisan::call('migrate', ['--force' => true]);
        $steps['migrate'] = trim(Artisan::output());

        Artisan::call('config:cache');
        $steps['config_cache'] = trim(Artisan::output());

        Artisan::call('route:cache');
        $steps['route_cache'] = trim(Artisan::output());

        Artisan::call('view:cache');
        $steps['view_cache'] = trim(Artisan::output());

        return response()->json(['status' => 'ok', 'steps' => $steps]);
    }
}
