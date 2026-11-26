<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Support;

use DataHealth\Mcp\Contracts\FindingAccess;
use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;

class PolicyFindingAccess implements FindingAccess
{
    /** @return Builder<FindingRecord> */
    public function query(Request $request): Builder
    {
        return FindingRecord::query();
    }

    public function authorize(Request $request, string $ability, ?FindingRecord $record = null): void
    {
        Gate::forUser($request->user())->authorize($ability, $record ?? FindingRecord::class);
    }

    public function allows(Request $request, string $ability, FindingRecord $record): bool
    {
        return Gate::forUser($request->user())->allows($ability, $record);
    }
}
