<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tests\Fixtures;

use DataHealth\Models\FindingRecord;
use Illuminate\Contracts\Auth\Authenticatable;

class FindingPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, FindingRecord $record): bool
    {
        return ! in_array($record->id, config('test.denied', []), true);
    }

    public function verify(?Authenticatable $user, FindingRecord $record): bool
    {
        return config('test.verify', true);
    }

    public function resolve(?Authenticatable $user, FindingRecord $record): bool
    {
        return config('test.resolve', true);
    }
}
