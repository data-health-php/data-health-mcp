<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tools;

use DataHealth\Contracts\CanResolve;
use DataHealth\Enums\RecordStatus;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive]
class ResolveFinding extends FindingTool
{
    protected string $name = 'resolve_finding';

    protected string $description = 'Execute the application resolver for one active finding. Can change business data and closes the finding on success. Requires authorization for this correction.';

    protected function execute(Request $request): Response|ResponseFactory
    {
        $record = $this->record($request);
        $this->access->authorize($request, 'resolve', $record);

        if ($record->status !== RecordStatus::Active) {
            return Response::error('Only active findings can be resolved.');
        }

        if (! $record->getFinding() instanceof CanResolve) {
            return Response::error('This finding does not support resolution.');
        }
        $resolved = $this->manager->resolve($record);

        return Response::structured([
            'outcome' => $resolved ? 'resolved' : 'not_resolved',
            'record' => $this->presenter->summary($record->refresh()),
        ]);
    }
}
