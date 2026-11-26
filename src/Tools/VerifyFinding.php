<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tools;

use DataHealth\Contracts\CanVerify;
use DataHealth\Enums\RecordStatus;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
class VerifyFinding extends FindingTool
{
    protected string $name = 'verify_finding';

    protected string $description = 'Recheck one active finding. A successful verification closes the finding; it does not correct business data. still_present is a valid check result, not a failed operation.';

    protected function execute(Request $request): Response|ResponseFactory
    {
        $record = $this->record($request);
        $this->access->authorize($request, 'verify', $record);

        if ($record->status !== RecordStatus::Active) {
            return Response::error('Only active findings can be verified.');
        }

        if (! $record->getFinding() instanceof CanVerify) {
            return Response::error('This finding does not support verification.');
        }
        $resolved = $this->manager->verify($record);

        return Response::structured([
            'outcome' => $resolved ? 'resolved' : 'still_present',
            'record' => $this->presenter->summary($record->refresh()),
        ]);
    }
}
