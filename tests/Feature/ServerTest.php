<?php

declare(strict_types=1);

use DataHealth\Mcp\DataHealthMcpServiceProvider;
use DataHealth\Mcp\DataHealthServer;
use DataHealth\Mcp\Tests\Fixtures\FindingPolicy;
use DataHealth\Mcp\Tools\GetFinding;
use DataHealth\Mcp\Tools\ListFindings;
use DataHealth\Mcp\Tools\ListFindingTypes;
use DataHealth\Mcp\Tools\ResolveFinding;
use DataHealth\Mcp\Tools\VerifyFinding;
use DataHealth\Models\FindingRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;

it('registers the local server and publishes its optional configuration', function () {
    expect(app(Registrar::class)->getLocalServer('data-health'))->toBeCallable()
        ->and(ServiceProvider::pathsToPublish(DataHealthMcpServiceProvider::class, 'data-health-mcp-config'))->not->toBeEmpty();
    expect(config('data-health-mcp.include_context'))->toBeFalse();
});

it('advertises the five tools with accurate side-effect annotations', function () {
    $tools = [ListFindingTypes::class, ListFindings::class, GetFinding::class, VerifyFinding::class, ResolveFinding::class];
    foreach ($tools as $tool) {
        $definition = app($tool)->toArray();
        expect($definition['annotations']['readOnlyHint'])->toBe(in_array($tool, [ListFindingTypes::class, ListFindings::class, GetFinding::class], true));
    }
    expect(app(ResolveFinding::class)->toArray()['annotations']['destructiveHint'])->toBeTrue();
    DataHealthServer::tools()->assertRegistered($tools);
});

it('does not automatically expose an HTTP endpoint', function () {
    expect(app(Registrar::class)->getWebServer('mcp/data-health'))->toBeNull();
});

it('serves tool calls through an explicitly registered HTTP endpoint', function () {
    Gate::policy(FindingRecord::class, FindingPolicy::class);
    Mcp::web('/mcp/data-health', DataHealthServer::class);

    $this->postJson('/mcp/data-health', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'list_finding_types', 'arguments' => (object) []],
    ])->assertOk()->assertJsonPath('result.structuredContent.data.0.key', 'ActionableFinding');
});

it('allows host authentication middleware to protect HTTP access', function () {
    Mcp::web('/mcp/protected-data-health', DataHealthServer::class)->middleware('auth');

    $this->postJson('/mcp/protected-data-health', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
    ])->assertUnauthorized();
});
