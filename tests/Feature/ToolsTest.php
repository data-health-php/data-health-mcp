<?php

declare(strict_types=1);

use DataHealth\Enums\RecordStatus;
use DataHealth\Mcp\Contracts\FindingAccess;
use DataHealth\Mcp\DataHealthServer;
use DataHealth\Mcp\Support\PolicyFindingAccess;
use DataHealth\Mcp\Tests\Fixtures\FindingPolicy;
use DataHealth\Mcp\Tests\Fixtures\Findings\ActionableFinding;
use DataHealth\Mcp\Tests\Fixtures\Findings\BasicFinding;
use DataHealth\Mcp\Tests\Fixtures\TestModel;
use DataHealth\Mcp\Tools\GetFinding;
use DataHealth\Mcp\Tools\ListFindings;
use DataHealth\Mcp\Tools\ListFindingTypes;
use DataHealth\Mcp\Tools\ResolveFinding;
use DataHealth\Mcp\Tools\VerifyFinding;
use DataHealth\Models\FindingRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;

beforeEach(function () {
    $this->model = TestModel::create(['name' => 'broken']);
    Gate::policy(FindingRecord::class, FindingPolicy::class);
});

it('lists finding types with descriptions and supported capabilities', function () {
    DataHealthServer::tool(ListFindingTypes::class)
        ->assertHasNoErrors()
        ->assertStructuredContentCount(2, 'data')
        ->assertStructuredContentPath('data.0.key', 'ActionableFinding')
        ->assertStructuredContentPath('data.0.capabilities.explain', true);
});

it('filters and paginates finding records without disclosing context or running explanations', function () {
    $record = ActionableFinding::found($this->model, ['fail' => true, 'secret' => 'private']);
    BasicFinding::found($this->model);

    DataHealthServer::tool(ListFindings::class, ['finding' => 'ActionableFinding', 'per_page' => 1])
        ->assertHasNoErrors()
        ->assertStructuredContentCount(1, 'data')
        ->assertStructuredContentPath('data.0.id', $record->id)
        ->assertStructuredContentPath('meta.total', 1)
        ->assertDontSee('private');
});

it('reads current explanations and action descriptions without changing record status', function () {
    $record = ActionableFinding::found($this->model, ['secret' => 'private']);
    $this->model->update(['name' => 'correct']);

    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()
        ->assertStructuredContentPath('explanation.actual.name', 'correct')
        ->assertStructuredContentPath('explanation.still_applies', false)
        ->assertStructuredContentPath('actions.resolve.description', 'Change the name to correct after checking the current state.')
        ->assertDontSee('private');

    expect($record->refresh()->status)->toBe(RecordStatus::Active);
});

it('supports findings without explanations and unavailable registered classes', function () {
    $record = BasicFinding::found($this->model);
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()->assertStructuredContentPath('explanation', null);
    $record->update(['key' => 'RemovedFinding']);
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()->assertStructuredContentPath('registered', false)
        ->assertStructuredContentPath('explanation', null);
});

it('reports unavailable explanations without disclosing exception details', function () {
    $record = ActionableFinding::found($this->model, ['fail' => true]);
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()->assertStructuredContentPath('explanation_error', 'The current explanation is unavailable.')
        ->assertDontSee('secret connection');
});

it('distinguishes a persistent problem from successful verification', function () {
    $record = ActionableFinding::found($this->model);
    DataHealthServer::tool(VerifyFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()->assertStructuredContentPath('outcome', 'still_present');
    expect($record->refresh()->status)->toBe(RecordStatus::Active);
    $this->model->update(['name' => 'correct']);
    DataHealthServer::tool(VerifyFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()->assertStructuredContentPath('outcome', 'resolved');
    expect($record->refresh()->status)->toBe(RecordStatus::Resolved);
});

it('runs application resolution and distinguishes an unsuccessful correction', function () {
    $failed = ActionableFinding::found($this->model, ['cannot_resolve' => true]);
    DataHealthServer::tool(ResolveFinding::class, ['id' => $failed->id])
        ->assertHasNoErrors()->assertStructuredContentPath('outcome', 'not_resolved');
    expect($failed->refresh()->status)->toBe(RecordStatus::Active);
    $record = ActionableFinding::found($this->model);
    DataHealthServer::tool(ResolveFinding::class, ['id' => $record->id])
        ->assertHasNoErrors()->assertStructuredContentPath('outcome', 'resolved');
    expect($record->refresh()->status)->toBe(RecordStatus::Resolved)
        ->and($this->model->refresh()->name)->toBe('correct');
});

it('denies tools by default when no finding policy is configured', function () {
    Gate::policy(FindingRecord::class, stdClass::class);
    DataHealthServer::tool(ListFindingTypes::class)->assertHasErrors(['not authorized']);
    DataHealthServer::tool(ListFindings::class)->assertHasErrors(['not authorized']);
});

it('enforces record and operation permissions', function () {
    $record = ActionableFinding::found($this->model);
    config()->set('test.denied', [$record->id]);
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])->assertHasErrors(['not authorized']);
    DataHealthServer::tool(ResolveFinding::class, ['id' => $record->id])->assertHasErrors(['not authorized']);
    config()->set('test.denied', []);
    config()->set('test.resolve', false);
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])->assertStructuredContentPath('actions.resolve.available', false);
    DataHealthServer::tool(ResolveFinding::class, ['id' => $record->id])->assertHasErrors(['not authorized']);
    expect($this->model->refresh()->name)->toBe('broken');
});

it('validates tool arguments', function (string $tool, array $arguments) {
    DataHealthServer::tool($tool, $arguments)->assertHasErrors();
})->with([
    [GetFinding::class, []], [GetFinding::class, ['id' => -1]],
    [ListFindings::class, ['status' => 'invalid']], [ListFindings::class, ['per_page' => 101]],
    [ListFindings::class, ['page' => 0]], [ListFindings::class, ['finding' => 'Unknown']],
]);

it('rejects missing records unsupported operations and inactive records', function (string $tool) {
    DataHealthServer::tool($tool, ['id' => 999])->assertHasErrors(['not found']);
    $record = BasicFinding::found($this->model);
    DataHealthServer::tool($tool, ['id' => $record->id])->assertHasErrors(['does not support']);
    $active = ActionableFinding::found($this->model);
    $active->update(['status' => RecordStatus::Ignored]);
    DataHealthServer::tool($tool, ['id' => $active->id])->assertHasErrors(['active']);
})->with([VerifyFinding::class, ResolveFinding::class]);

it('reports operation failures without closing findings or exposing internals', function (string $tool) {
    $record = ActionableFinding::found($this->model, ['fail' => true]);
    DataHealthServer::tool($tool, ['id' => $record->id])->assertHasErrors(['could not be completed'])->assertDontSee('secret connection');
    expect($record->refresh()->status)->toBe(RecordStatus::Active);
})->with([VerifyFinding::class, ResolveFinding::class]);

it('applies the same access scope to pagination details and mutations', function () {
    $visible = ActionableFinding::found($this->model);
    $hidden = ActionableFinding::found(TestModel::create(['name' => 'other tenant']));
    app()->bind(FindingAccess::class, fn () => new class($visible->id) extends PolicyFindingAccess
    {
        public function __construct(private int $visibleId) {}

        public function query(Request $request): Builder
        {
            return parent::query($request)->whereKey($this->visibleId);
        }
    });

    DataHealthServer::tool(ListFindings::class)->assertHasNoErrors()
        ->assertStructuredContentCount(1, 'data')->assertStructuredContentPath('meta.total', 1);
    foreach ([GetFinding::class, VerifyFinding::class, ResolveFinding::class] as $tool) {
        DataHealthServer::tool($tool, ['id' => $hidden->id])->assertHasErrors(['not found']);
    }
    expect($hidden->refresh()->status)->toBe(RecordStatus::Active);
});

it('only includes stored context when explicitly enabled', function () {
    $record = BasicFinding::found($this->model, ['reference' => 'external-123']);
    config()->set('data-health-mcp.include_context', true);
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])->assertHasNoErrors()
        ->assertStructuredContentPath('context.reference', 'external-123');
});

it('filters all supported record fields and returns the requested page', function () {
    $first = ActionableFinding::found($this->model);
    $second = ActionableFinding::found(TestModel::create(['name' => 'second']));
    foreach ([$first, $second] as $record) {
        $record->update(['status' => RecordStatus::Ignored, 'urgency' => 'soon', 'worklist' => 'billing']);
    }
    BasicFinding::found($this->model);
    DataHealthServer::tool(ListFindings::class, [
        'status' => 'ignored', 'urgency' => 'soon', 'worklist' => 'billing', 'page' => 2, 'per_page' => 1,
    ])->assertHasNoErrors()->assertStructuredContentPath('data.0.id', $second->id)
        ->assertStructuredContentPath('meta.total', 2);
});

it('does not equate missing affected models with successful checks', function () {
    $record = ActionableFinding::found($this->model);
    $this->model->delete();
    DataHealthServer::tool(GetFinding::class, ['id' => $record->id])->assertHasNoErrors()
        ->assertStructuredContentPath('explanation', null)
        ->assertStructuredContentPath('explanation_error', 'The current explanation is unavailable.');
    DataHealthServer::tool(VerifyFinding::class, ['id' => $record->id])->assertHasErrors();
    expect($record->refresh()->status)->toBe(RecordStatus::Active);
});
