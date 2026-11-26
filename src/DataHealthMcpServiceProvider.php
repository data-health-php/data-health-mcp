<?php

declare(strict_types=1);

namespace DataHealth\Mcp;

use DataHealth\Mcp\Contracts\FindingAccess;
use DataHealth\Mcp\Support\PolicyFindingAccess;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

class DataHealthMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data-health-mcp.php', 'data-health-mcp');
        $this->app->bindIf(FindingAccess::class, PolicyFindingAccess::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $name = config('data-health-mcp.local_server');

            if (is_string($name) && $name !== '') {
                Mcp::local($name, DataHealthServer::class);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/data-health-mcp.php' => config_path('data-health-mcp.php'),
            ], 'data-health-mcp-config');
        }
    }
}
