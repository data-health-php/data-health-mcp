<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tools;

use DataHealth\DataHealthManager;
use DataHealth\FindingRegistry;
use DataHealth\Mcp\Contracts\FindingAccess;
use DataHealth\Mcp\Support\FindingPresenter;
use DataHealth\Models\FindingRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

abstract class FindingTool extends Tool
{
    public function __construct(
        protected FindingAccess $access,
        protected FindingPresenter $presenter,
        protected DataHealthManager $manager,
        protected FindingRegistry $registry,
    ) {}

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->min(1)->description('The finding record ID.')->required()];
    }

    final public function handle(Request $request): Response|ResponseFactory
    {
        try {
            return $this->execute($request);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthorizationException) {
            return Response::error('This action is not authorized.');
        } catch (ModelNotFoundException) {
            return Response::error('The finding or affected record was not found or is inaccessible.');
        } catch (Throwable $exception) {
            report($exception);

            return Response::error('The operation could not be completed. No successful result is confirmed. Inspect the application logs before retrying.');
        }
    }

    protected function record(Request $request): FindingRecord
    {
        $arguments = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $record = $this->access->query($request)->whereKey($arguments['id'])->firstOrFail();
        $this->access->authorize($request, 'view', $record);

        return $record;
    }

    abstract protected function execute(Request $request): Response|ResponseFactory;
}
