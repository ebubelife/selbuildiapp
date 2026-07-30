<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class DeployController extends Controller
{
    /**
     * Runs post-deploy tasks (composer install, migrations, cache warming)
     * over a plain HTTP request instead of SSH, since non-interactive SSH
     * command execution is unreliable on this shared hosting account while
     * normal web requests are not.
     */
    public function run(Request $request): JsonResponse
    {
        $token = (string) config('services.deploy.token');

        if ($token === '' || ! hash_equals($token, (string) $request->input('token'))) {
            abort(403);
        }

        set_time_limit(300);

        $steps = [];

        $steps['composer_install'] = $this->runComposerInstall();

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

    private function runComposerInstall(): string
    {
        $basePath = base_path();
        $composerPhar = $basePath.'/composer.phar';

        $composerBin = 'composer';

        $which = new Process(['which', 'composer']);
        $which->run();

        if (! $which->isSuccessful()) {
            if (! file_exists($composerPhar)) {
                $installer = $basePath.'/composer-setup.php';
                copy('https://getcomposer.org/installer', $installer);

                $setup = new Process(['php', $installer, '--install-dir='.$basePath, '--filename=composer.phar']);
                $setup->setTimeout(120);
                $setup->run();

                @unlink($installer);
            }

            $composerBin = 'php '.$composerPhar;
        }

        $process = Process::fromShellCommandline(
            $composerBin.' install --no-dev --optimize-autoloader --no-interaction --prefer-dist',
            $basePath,
            ['COMPOSER_MEMORY_LIMIT' => '-1'],
        );
        $process->setTimeout(280);
        $process->run();

        return trim($process->getOutput()."\n".$process->getErrorOutput());
    }
}
