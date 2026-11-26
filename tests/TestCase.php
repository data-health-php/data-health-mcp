<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tests;

use DataHealth\DataHealthServiceProvider;
use DataHealth\Mcp\DataHealthMcpServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DataHealthServiceProvider::class, McpServiceProvider::class, DataHealthMcpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('data-health.scheduler.enabled', false);
        $app['config']->set('data-health.directories', [
            '../../../../tests/Fixtures/Findings' => 'DataHealth\\Mcp\\Tests\\Fixtures\\Findings',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (glob(__DIR__.'/../vendor/data-health/data-health/database/migrations/*.php') as $migration) {
            (require $migration)->up();
        }
        Schema::create('test_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
