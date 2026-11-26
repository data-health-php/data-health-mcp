<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tools;

use DataHealth\Enums\FindingUrgency;
use DataHealth\Enums\RecordStatus;
use DataHealth\Models\FindingRecord;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListFindings extends FindingTool
{
    protected string $name = 'list_findings';

    protected string $description = 'Filter and paginate visible finding records. Defaults to active records. Does not run explanations or detection.';

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['active', 'ignored', 'resolved', 'all'])->default('active'),
            'finding' => $schema->string()->description('A registered finding key.'),
            'urgency' => $schema->string()->enum(array_column(FindingUrgency::cases(), 'value')),
            'worklist' => $schema->string(),
            'page' => $schema->integer()->min(1)->default(1),
            'per_page' => $schema->integer()->min(1)->max(100)->default(20),
        ];
    }

    protected function execute(Request $request): ResponseFactory
    {
        $this->access->authorize($request, 'viewAny');
        $arguments = $request->validate([
            'status' => ['sometimes', Rule::in([...array_column(RecordStatus::cases(), 'value'), 'all'])],
            'finding' => ['sometimes', 'string', 'min:1'],
            'urgency' => ['sometimes', Rule::enum(FindingUrgency::class)],
            'worklist' => ['sometimes', 'string', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->access->query($request);
        $status = $arguments['status'] ?? 'active';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (isset($arguments['finding'])) {
            if (! $this->registry->all()->has($arguments['finding'])) {
                throw ValidationException::withMessages(['finding' => 'The finding key is not registered.']);
            }
            $query->where('key', $arguments['finding']);
        }
        foreach (['urgency', 'worklist'] as $filter) {
            if (isset($arguments[$filter])) {
                $query->where($filter, $arguments[$filter]);
            }
        }
        $page = $query->orderBy('id')->paginate((int) ($arguments['per_page'] ?? 20), ['*'], 'page', (int) ($arguments['page'] ?? 1));

        return Response::structured([
            'data' => $page->getCollection()->map(fn (FindingRecord $record): array => $this->presenter->summary($record))->all(),
            'meta' => [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
