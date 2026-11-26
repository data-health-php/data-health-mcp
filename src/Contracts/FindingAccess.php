<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Contracts;

use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;

interface FindingAccess
{
    /**
     * Scope every list and record lookup to the client's visible records.
     * Pagination totals are computed from this query, so apply tenant boundaries here.
     *
     * @return Builder<FindingRecord>
     */
    public function query(Request $request): Builder;

    /** Throw an AuthorizationException when the ability is denied. */
    public function authorize(Request $request, string $ability, ?FindingRecord $record = null): void;

    public function allows(Request $request, string $ability, FindingRecord $record): bool;
}
