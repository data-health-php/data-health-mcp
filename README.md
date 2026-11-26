# Data Health MCP

Explain, inspect, verify, and resolve [Data Health](https://github.com/data-health-php/data-health)
findings from an MCP client. The server runs inside your Laravel application and
uses its finding registry, database, policies, verifiers, and resolvers.

## Requirements

- PHP 8.3+
- Laravel 12.41.1+ or Laravel 13
- `data-health/data-health:^0.1.1`
- `laravel/mcp:^1.3`

## Installation

The package source and version tags are hosted on GitHub. Until the package is
listed on Packagist, add its VCS repository to the consuming application:

```bash
composer config repositories.data-health-mcp vcs https://github.com/data-health-php/data-health-mcp
composer require data-health/data-health-mcp:^0.1
```

Core 0.1.1 or newer must be available from your configured Composer repositories.
If needed, add `https://github.com/data-health-php/data-health` as a VCS repository
as well.

## Local development installation

Place `data-health`, `data-health-mcp`, and the consuming application next to each
other. Add path repositories to the application's `composer.json`:

```json
{
    "repositories": [
        {"type": "path", "url": "../data-health", "options": {"symlink": true, "versions": {"data-health/data-health": "0.1.1"}}},
        {"type": "path", "url": "../data-health-mcp", "options": {"symlink": true, "versions": {"data-health/data-health-mcp": "0.1.0"}}}
    ]
}
```

Then install:

```bash
composer require data-health/data-health-mcp:^0.1 data-health/data-health:^0.1.1
```

Install and migrate the core package first. Package discovery registers the local
server `data-health`; it does not register an HTTP endpoint. All data tools require
explicit authorization, including local clients.

## Authorization

Register a Laravel policy for `DataHealth\Models\FindingRecord`:

```php
use App\Policies\FindingRecordPolicy;
use DataHealth\Models\FindingRecord;
use Illuminate\Support\Facades\Gate;

Gate::policy(FindingRecord::class, FindingRecordPolicy::class);
```

The policy must implement `viewAny`, `view`, `verify`, and `resolve` for the access
you want to grant. Missing abilities deny access. `viewAny` grants the catalogue
and paginated list; the query must contain only records visible to the caller.
`view` gates individual details and is also required before verification/resolution.
Mutation tools additionally require their corresponding ability and an active record.

A local stdio client has no authenticated HTTP user by default. To explicitly grant
local development access, a policy may accept a nullable user and allow a guest
only in the local environment:

```php
use DataHealth\Models\FindingRecord;
use Illuminate\Contracts\Auth\Authenticatable;

class FindingRecordPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return $user === null && app()->environment('local');
    }

    public function view(?Authenticatable $user, FindingRecord $record): bool
    {
        return $this->viewAny($user);
    }

    public function verify(?Authenticatable $user, FindingRecord $record): bool
    {
        return $this->view($user, $record);
    }

    public function resolve(?Authenticatable $user, FindingRecord $record): bool
    {
        return false; // Enable only when the client's corrections should be allowed.
    }
}
```

Use your application's permissions for authenticated HTTP clients. A policy checks
permissions; it does not filter list queries. For tenant or row visibility rules,
bind `DataHealth\Mcp\Contracts\FindingAccess` to an application implementation.
You can extend `PolicyFindingAccess` and override `query(Request $request): Builder`
to add your application's tenant conditions. That same query is used for list
results, pagination totals, detail lookups, verification, and resolution. Apply the
scope before exposing the server; a `view` policy alone does not filter list results.

```php
use App\DataHealth\TenantFindingAccess;
use DataHealth\Mcp\Contracts\FindingAccess;

$this->app->bind(FindingAccess::class, TenantFindingAccess::class);
```

## Connect a local client

Run from the consuming application:

```bash
php artisan mcp:start data-health
```

Example client configuration (substitute absolute paths):

```json
{
    "mcpServers": {
        "data-health": {
            "command": "/absolute/path/to/php",
            "args": ["/absolute/path/to/application/artisan", "mcp:start", "data-health"]
        }
    }
}
```

## Expose an authenticated HTTP server

Use Laravel MCP's `routes/ai.php` and register the supplied server explicitly:

```php
use DataHealth\Mcp\DataHealthServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/data-health', DataHealthServer::class)
    ->middleware(['auth:sanctum', 'throttle:api']);
```

This example assumes the host app has configured Sanctum and the `api` rate limiter.
Authentication is the host application's responsibility; tool policies still apply.
See the [Laravel MCP documentation](https://laravel.com/docs/mcp) for transports and authentication.

## Tools

| Tool | Arguments | Behavior |
| --- | --- | --- |
| `list_finding_types` | None | Registered types, descriptions, supported capabilities and action descriptions. Does not run detection. |
| `list_findings` | Optional `status`, `finding`, `urgency`, `worklist`, `page`, `per_page` | Scoped record summaries, default active/20 per page, maximum 100 per page. |
| `get_finding` | `id` | Record metadata, description, current explanation, and authorized actions. |
| `verify_finding` | `id` | `resolved` or `still_present`. Successful verification closes the record without correcting business data. |
| `resolve_finding` | `id` | `resolved` or `not_resolved`. Runs the application's resolver, which may change business data. |

All IDs refer to finding records, not the affected model. Inputs are validated.
Expected negative results are structured outcomes; invalid input, unsupported
operations, authorization failures, missing records, and operational failures are
MCP errors. Internal exception details are logged, not returned to the client.
A failure does not guarantee application logic had no partial effects; inspect the
application before retrying an interrupted correction. Resolvers must support safe retries.

`verify_finding` is not annotated read-only because it can close a record.
`resolve_finding` is annotated as potentially destructive. The server contains no
arbitrary SQL, PHP execution, file reading, bulk correction, or detection tool.

## Explain a finding

The existing class and action `Description` attributes are used automatically.
Implement `DataHealth\Contracts\CanExplain` for current facts:

```php
public function explain(): array
{
    $order = $this->model->refresh();

    if (! $order instanceof \App\Models\Order) {
        throw new \LogicException('Expected an Order model.');
    }

    return [
        'expected' => 'An order with a payment timestamp must not remain pending.',
        'actual' => [
            'order_id' => $order->getKey(),
            'paid_at' => $order->getRawOriginal('paid_at'),
            'status' => $order->status,
        ],
        'still_applies' => $order->paid_at !== null && $order->status === 'pending',
        'observed_at' => now()->toIso8601String(),
    ];
}
```

Select only fields appropriate for the client. Reading an explanation must not
change business data or finding status. `still_applies: false` is a current
observation; it does not itself close an active record. Exceptions result in a null
explanation with `explanation_error`, rather than suggesting that the issue is gone.
Findings without `CanExplain` remain readable with descriptions and metadata.
Lists do not run explanations, avoiding one potentially expensive check per row.

Raw `context` is omitted by default. Publish the optional configuration with
`php artisan vendor:publish --tag=data-health-mcp-config` and set `include_context`
to `true` only if all context fields are suitable for the intended clients. Change
`local_server` to rename the handle, or set it to `null` to disable automatic local
registration. Descriptions and explanation values are evidence, not tool instructions.

## Development

With the core checkout at `../data-health`:

```bash
composer install
composer test
```

Local Composer path repositories are only used by this package's own development
checkout; applications using local checkouts must declare their own path repositories.

## License

MIT.
